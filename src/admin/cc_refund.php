<?php
/**
 * @return bool|void
 * @throws \Exception
 * @throws \SmartyException
 */
function cc_refund()
{
	page_title('CC Refund');
	function_requirements('has_acl');
	if ($GLOBALS['tf']->ima != 'admin' || !has_acl('client_billing')) {
		dialog('Not admin', 'Not Admin or you lack the permissions to view this page.');
		return false;
	}
	function_requirements('class.AuthorizeNetCC');
	$continue = false;
	if (!isset($GLOBALS['tf']->variables->request['transact_id'])) {
		add_output('Transaction ID is empty!');
		return;
	}
	$GLOBALS['tf']->variables->request['transact_id'] = (int)$GLOBALS['tf']->variables->request['transact_id'];
	$query = "select * from cc_log where cc_result_trans_id='{$GLOBALS['tf']->variables->request['transact_id']}'";
	$db = clone $GLOBALS['tf']->db;
	$db->query($query, __LINE__, __FILE__);
	if ($db->num_rows() == 0) {
		add_output('no cc_log entry found');
		return;
	}
	$db->next_record(MYSQL_ASSOC);
	$cc_log = $db->Record;
	$desc = "Credit Card Payment {$GLOBALS['tf']->variables->request['transact_id']}";
	if (isset($GLOBALS['tf']->variables->request['amount'])) {
		$transactAmount = $GLOBALS['tf']->variables->request['amount'];
	}
	$db_check_invoice = clone $GLOBALS['tf']->db;
	$db_check_invoice->query("SELECT * FROM invoices WHERE invoices_custid={$db->Record['cc_custid']} and invoices_description='Credit Card Payment {$GLOBALS['tf']->variables->request['transact_id']}'", __LINE__, __FILE__);
	if ($db_check_invoice->num_rows() > 0) {
		$invoice_arr = [];
		while ($db_check_invoice->next_record(MYSQL_ASSOC)) {
			$invoice_arr[] = $db_check_invoice->Record['invoices_id'];
		}
	}
	$GLOBALS['tf']->variables->request['inv'] = implode(',', $invoice_arr);
	$db = clone $GLOBALS['tf']->db;
	$dbR = clone $GLOBALS['tf']->db;
	$db->query("SELECT * FROM invoices WHERE invoices_id IN ({$GLOBALS['tf']->variables->request['inv']})");
	$checkbox = '';
	if ($db->num_rows() > 0) {
		while ($db->next_record(MYSQL_ASSOC)) {
			//Get all refund Invoices for the transaction
			$dbR->query("SELECT * FROM invoices WHERE invoices_extra = {$db->Record['invoices_id']}");
			if ($dbR->num_rows() == 0) {
				$date = date('Y-m-d 23:59:59', strtotime());
				$do = strtotime(date('Y-m-d', strtotime($cc_log['cc_timestamp']))) == strtotime(date('Y-m-d')) ? 'void' : 'refund';
				$serviceAmount[$db->Record['invoices_id']] = $db->Record['invoices_amount'];
				if ($do == 'void') {
					$checkbox .= '<input type="checkbox" name="refund_amount_opt[]" value="'.$db->Record['invoices_service'].'_'.$db->Record['invoices_id'].'_'.$db->Record['invoices_amount'].'" onclick="return false;" checked readonly>&nbsp;<label for="" style="text-transform: capitalize;"> '.$db->Record['invoices_module'].' '.$db->Record['invoices_service'].' $' .$db->Record['invoices_amount'].'</label><br>';
				} else {
					$checkbox .= '<input type="checkbox" name="refund_amount_opt[]" value="'.$db->Record['invoices_service'].'_'.$db->Record['invoices_id'].'_'.$db->Record['invoices_amount'].'" onclick="return update_partial_payment();" checked>&nbsp;<label for="" style="text-transform: capitalize;"> '.$db->Record['invoices_module'].' '.$db->Record['invoices_service'].' $' .$db->Record['invoices_amount'].'</label><br>';
				}
			} else {
				$alreadyRefundedAmount = 0;
				while($dbR->next_record(MYSQL_ASSOC)) {
					$alreadyRefundedAmount += $dbR->Record['invoices_amount'];
				}
				$transactAmount = $transactAmount - $alreadyRefundedAmount;
				$checkbox .= '<input type="checkbox" name="refund_amount_opt[]" value="'.$db->Record['invoices_service'].'_'.$db->Record['invoices_id'].'_0" disabled="disabled">&nbsp;<label for="" style="text-transform: capitalize;"> '.$db->Record['invoices_module'].' '.$db->Record['invoices_service'].' $' .$db->Record['invoices_amount'].'</label><br>';
			}			
		}
	}
	if (!isset($GLOBALS['tf']->variables->request['confirmed']) || !verify_csrf('cc_refund')) {
		$db = clone $GLOBALS['tf']->db;
		$invoices_arr = explode(',', $GLOBALS['tf']->variables->request['inv']);
		$table = new TFTable;
		$table->set_options('cellpadding="10px" cellspacing="10px"');
		$table->set_form_options('id="ccrefundform"');
		$table->csrf('cc_refund');
		$table->set_title('Confirm Refund');
		$table->set_post_location('index.php');
		$table->add_hidden('transact_id', $GLOBALS['tf']->variables->request['transact_id']);
		$table->add_hidden('card', $GLOBALS['tf']->variables->request['card']);
		$table->add_hidden('cust_id', $GLOBALS['tf']->variables->request['cust_id']);
		$table->add_hidden('module', $GLOBALS['tf']->variables->request['module']);
		$table->add_hidden('inv', $GLOBALS['tf']->variables->request['inv']);
		$table->add_hidden('amount', $transactAmount);
		$table->add_hidden('transact_id', $GLOBALS['tf']->variables->request['transact_id']);
		$table->add_field('Services', 'l');
		$table->add_field($checkbox, 'l');
		$table->add_row();
		$table->add_field('Amount To be Refund', 'l');
		$table->add_field($table->make_input('refund_amount', $transactAmount, 25, false, 'id="partialtext"'), 'l');
		$table->add_row();
		$table->add_field('Set Charge Invoice Unpaid', 'l');
		$table->add_field($table->make_radio('unpaid', 'yes').' Yes&nbsp; '.$table->make_radio('unpaid', 'no', 'no').' No', 'l');
		$table->add_row();
		$table->set_colspan(2);
		$table->add_hidden('confirmed', 'yes');
		$table->add_field($table->make_submit('Submit','submit','confirm','onclick="return confirm_dialog(event);"'));
		$table->add_row();
		add_output($table->get_table());
		$script = '<script>
		function update_partial_payment() {
			var ret = 0;
			$(\'input[type=checkbox]\').each(function () {
				if (this.checked) {
					var gg = $(this).val().split("_");
					ret += parseFloat(gg[2]);
				}
			});
			$(\'#partialtext\').val(ret.toFixed(2));

		}
		function confirm_dialog(event) {
			event.preventDefault();
			var c = confirm("Are you sure want to refund?");
			if(c){
				$("form#ccrefundform").submit();
			  }
		}
		</script>';
		add_output($script);
	} elseif (isset($GLOBALS['tf']->variables->request['confirmed']) && $GLOBALS['tf']->variables->request['confirmed'] == 'yes') {
		$continue = true;
		$transact_ID = $GLOBALS['tf']->variables->request['transact_id'];
		if ($GLOBALS['tf']->variables->request['amount'] < $GLOBALS['tf']->variables->request['refund_amount']) {
			add_output('<div class="alert alert-danger">Error! Refund amount greater than paid amount, must be lesser or equal.</div>');
			$continue = false;
		}
		if ($GLOBALS['tf']->variables->request['refund_amount'] <= 0) {
			add_output('Error! You entered Refund amount less than or equal to $0. Refund amount must be greater than $0.');
			$continue = false;
		}
		if ($continue === true) {
			myadmin_log('admin', 'info', 'Going with CC Refund', __LINE__, __FILE__);
			foreach ($GLOBALS['tf']->variables->request['refund_amount_opt'] as $values) {
				$explodedValues = explode('_', $values);
				$invoiceIds[] = $explodedValues[1];
			}
			if ($GLOBALS['tf']->variables->request['amount'] == $GLOBALS['tf']->variables->request['refund_amount']) {
				$refund_type = 'Full';
			} else {
				$refund_type = 'Partial';
			}
			$amount = $GLOBALS['tf']->variables->request['refund_amount'];
			myadmin_log('admin', 'info', 'Refund ('.$refund_type.') amount : '.$amount, __LINE__, __FILE__);
			if (isset($GLOBALS['tf']->variables->request['card'])) {
				$card = $GLOBALS['tf']->variables->request['card'];
			}
			if (isset($GLOBALS['tf']->variables->request['cust_id'])) {
				$cust_id = $GLOBALS['tf']->variables->request['cust_id'];
			}
			if (isset($GLOBALS['tf']->variables->request['inv'])) {
				$invoice_id = $GLOBALS['tf']->variables->request['inv'];
			}
			$refundTransactionID = $transact_ID;
			$cc_num = mb_substr($card, -4);
			$cc_obj = new AuthorizeNetCC;
			if ($do == 'void') {
				$response_new = $cc_obj->voidTransaction($transact_ID, $cc_num, $cust_id);
				myadmin_log('admin', 'info', 'Going with CC void transaction', __LINE__, __FILE__);
			} else {
				$response = $cc_obj->refund($cc_num, $transact_ID, $amount, $cust_id);
				myadmin_log('admin', 'info', 'Going with CC Refund', __LINE__, __FILE__);
			}
			$status = ['', 'Approved', 'Declined', 'Error', 'Held for review'];
			$invoice_update = false;
			if ($status[$response['0']] == 'Approved') {
				$invoice_update = true;
				$refundTransactionID = $response[6] == 0 ? $transact_ID : $response[6];
			}
			add_output('Status : '.$status[$response['0']].' <br>Status Text: '.$response['3']);
			myadmin_log('admin', 'info', json_encode($response), __LINE__, __FILE__);
			$st_txt = $status[$response['0']] == 'Declined' || $status[$response['0']] == 'Error' ? $status[$response_new['0']].'! '.$response_new['3'] : $status[$response['0']].'! '.$response['3'];
			if ($invoice_update) {
				$db = clone $GLOBALS['tf']->db;
				$dbC = clone $GLOBALS['tf']->db;
				$dbD = clone $GLOBALS['tf']->db;
				$dbU = clone $GLOBALS['tf']->db;
				myadmin_log('payments', 'info', "Invoices selected for refund - ".json_encode($invoiceIds), __LINE__, __FILE__);
				$now = mysql_now();
				$amountRemaining = $amount;
				$invTotal = count($invoiceIds);
				$invLoop = 0;
				foreach ($invoiceIds as $inv) {
					myadmin_log('admin', 'debug', "SELECT * FROM invoices WHERE invoices_id = {$inv}", __LINE__, __FILE__);
					$dbC->query("SELECT * FROM invoices WHERE invoices_id = {$inv}");
					myadmin_log('admin', 'debug', "Payment Invoice record ".$dbC->num_rows(), __LINE__, __FILE__);
					if ($dbC->num_rows() > 0) {
						$dbC->next_record(MYSQL_ASSOC);
						$updateInv = $dbC->Record;
						if (++$invLoop == $invTotal) {
							$amount = $amountRemaining;
						} elseif ($refund_type == 'Full' || $amountRemaining >= $dbC->Record['invoices_amount']) {
							$amount = $dbC->Record['invoices_amount'];
						} else {
							$amount = $amountRemaining;
						}
						$amountRemaining = bcsub($amountRemaining, $amount);
						$invUpdateAmount = bcsub($dbC->Record['invoices_amount'], $amount, 2);
						$dbD->query("SELECT * FROM invoices WHERE invoices_id = {$dbC->Record['invoices_extra']}");
						$dbD->next_record(MYSQL_ASSOC);
						$invoice = new \MyAdmin\Orm\Invoice($db);
						$refund_created = $invoice->setDescription("REFUND: {$updateInv['invoices_description']}")
							->setAmount($amount)
							->setCustid($updateInv['invoices_custid'])
							->setType(2)
							->setDate($now)
							->setGroup(0)
							->setDueDate($now)
							->setExtra($inv)
							->setService($updateInv['invoices_service'])
							->setPaid(0)
							->setModule($updateInv['invoices_module'])
							->save();
						if ($refund_created) {
							myadmin_log('payments', 'debug', "Refund Invoice created for the amount {$amount}.", __LINE__, __FILE__);
						}
						if ($GLOBALS['tf']->variables->request['unpaid'] == 'yes') {
							$dbU->query("UPDATE invoices SET invoices_paid = 0 WHERE invoices_id = {$updateInv['invoices_extra']}");
						}
						$db->query(make_insert_query('history_log', [
							'history_id' => null,
							'history_sid' => $GLOBALS['tf']->session->sessionid,
							'history_timestamp' => mysql_now(),
							'history_creator' => $GLOBALS['tf']->session->account_id,
							'history_owner' => $cust_id,
							'history_section' => 'cc_refund',
							'history_type' => $transact_ID,
							'history_new_value' => "Refunded {$amount}",
							'history_old_value' => "Invoice Amount {$dbC->Record['invoices_amount']}"
						]), __LINE__, __FILE__);
					}
				}
			}
			$GLOBALS['tf']->redirect($GLOBALS['tf']->link('index.php', 'choice=none.view_cc_transaction&transaction='.$refundTransactionID.'&module='.$GLOBALS['tf']->variables->request['module'].'&st_txt='.$st_txt));
		}
	}
}
