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
	$desc = "Credit Card Payment {$GLOBALS['tf']->variables->request['transact_id']}";
	if (isset($GLOBALS['tf']->variables->request['amount'])) {
		$transactAmount = $GLOBALS['tf']->variables->request['amount'];
	}
	$db_check_invoice = clone $GLOBALS['tf']->db;
	$db_check_invoice->query("SELECT * FROM invoices WHERE LOWER(invoices_description) LIKE '$desc'");
	if ($db_check_invoice->num_rows() > 0) {
		$invoice_arr = [];
		while ($db_check_invoice->next_record(MYSQL_ASSOC)) {
			$invoice_arr[] = $db_check_invoice->Record['invoices_id'];
		}
	}
	$GLOBALS['tf']->variables->request['inv'] = implode(',', $invoice_arr);
	$db = clone $GLOBALS['tf']->db;
	$db->query("SELECT * FROM invoices WHERE invoices_id IN ({$GLOBALS['tf']->variables->request['inv']})");
	$select_serv = '<select name="refund_amount_opt" onchange="update_partial_row()">';
	$select_serv .= '<optgroup label="Refund All Services">';
	$select_serv .= '<option value="Full">$'.$transactAmount.' Refund Full Amount</option>';
	$select_serv .= '</optgroup>';
	$select_serv .= '<optgroup label="Refund Any Service">';
	if ($db->num_rows() > 0) {
		while ($db->next_record(MYSQL_ASSOC)) {
			if (!isset($GLOBALS['tf']->variables->request['cust_id']) || !$GLOBALS['tf']->variables->request['cust_id'] || $GLOBALS['tf']->variables->request['cust_id'] == 0) {
				$GLOBALS['tf']->variables->request['cust_id'] = $db->Record['invoices_custid'];
			}
			$serviceAmount[$db->Record['invoices_id']] = $db->Record['invoices_amount'];
			$select_serv .= '<option value="'.$db->Record['invoices_service'].'_'.$db->Record['invoices_id'].'_'.$db->Record['invoices_amount'].'">'.'$'.$db->Record['invoices_amount'].' '.$db->Record['invoices_module'].' '.$db->Record['invoices_service'].'</option>';
		}
	}
	$select_serv .= '</optgroup>';
	$select_serv .= '</select>';
	if (!isset($GLOBALS['tf']->variables->request['confirmation']) || !verify_csrf('cc_refund')) {
		$db = clone $GLOBALS['tf']->db;
		$invoices_arr = explode(',', $GLOBALS['tf']->variables->request['inv']);
		$table = new TFTable;
		$table->set_options('cellpadding="10px" cellspacing="10px"');
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
		$table->add_field($select_serv, 'l');
		$table->add_row();
		$table->add_field('Amount To be Refund', 'l');
		$table->add_field($table->make_input('refund_amount', $transactAmount, 25, false, 'id="partialtext"'), 'l');
		$table->add_row();
		$table->add_field('Refund Options', 'l');
		$table->add_field($table->make_radio('refund_opt', 'API', 'API') . 'Adjust the payment invoice', 'l');
		$table->add_row();
		$table->add_field("", 'l');
		$table->add_field($table->make_radio('refund_opt', 'APISCIU') . 'Adjust payment invoice + set charge invoice unpaid', 'l');
		$table->add_row();
		$table->add_field("", 'l');
		$table->add_field($table->make_radio('refund_opt', 'DPIDCI') . 'Delete payment invoice + Delete charge invoice', 'l');
		$table->add_row();
		$table->add_field("", 'l');
		$table->add_field($table->make_radio('refund_opt', 'JRM') . 'Just Refund the money', 'l');
		$table->add_row();
		$table->add_field('Are you sure want to Refund ?', 'l');
		$table->add_field($table->make_radio('confirmation', 'Yes', 'Yes') . 'Yes' . $table->make_radio('confirmation', 'No', true) . 'No', 'l');
		$table->add_row();
		$table->set_colspan(2);
		$table->add_field($table->make_submit('Confirm'));
		$table->add_row();
		add_output($table->get_table());
		$script = '<script>
		$(function(){
			update_partial_row();
		});
		function update_partial_row() {
			opt_val = $("select[name=\'refund_amount_opt\']").val();
			if(opt_val == \'Full\') {
				$("select[name=\'refund_amount_opt\']").parents("tr").next().hide();
			} else {
				selectedAmount = opt_val.split("_");
				$("#partialtext").val(selectedAmount[2]);
				$("select[name=\'refund_amount_opt\']").parents("tr").next().show();
			}
		}
		</script>';
		add_output($script);
	} elseif (isset($GLOBALS['tf']->variables->request['confirmation']) && $GLOBALS['tf']->variables->request['confirmation'] === 'Yes') {
		$continue = true;
		$transact_ID = $GLOBALS['tf']->variables->request['transact_id'];
		if ($GLOBALS['tf']->variables->request['refund_amount_opt'] == 'Full') {
			$continue = true;
			$refund_type = 'Full';
			$invoiceAmount = $GLOBALS['tf']->variables->request['amount'];
		} else {
			list($serviceId, $invoiceId, $invoiceAmount) = explode('_', $GLOBALS['tf']->variables->request['refund_amount_opt']);
			if ($invoiceAmount == $GLOBALS['tf']->variables->request['refund_amount'] || $GLOBALS['tf']->variables->request['refund_amount'] <= $invoiceAmount) {
				$continue = true;
				$refund_type = 'Partial';
				/*if ($invoiceAmount == $GLOBALS['tf']->variables->request['refund_amount']) {
					$refund_type = 'Full';
				} else {
					$refund_type = 'Partial';
				}*/
			} else {
				$continue = false;
				add_output('Error! You entered Refund amount is greater than invoice amount. Refund amount must be equal or lesser than invoice amount.');
				return;
			}
		}
		if ($continue === true) {
			myadmin_log('admin', 'info', 'Going with CC Refund', __LINE__, __FILE__);
			if ($refund_type == 'Full') {
				$amount = $GLOBALS['tf']->variables->request['amount'];
			} else {
				$amount = $GLOBALS['tf']->variables->request['refund_amount'];
			}
			myadmin_log('admin', 'info', 'Refund amount : '.$amount, __LINE__, __FILE__);
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
			$response = $cc_obj->refund($cc_num, $transact_ID, $amount, $cust_id);
			$status = ['', 'Approved', 'Declined', 'Error', 'Held for review'];
			$invoice_update = false;
			if ($status[$response['0']] == 'Approved') {
				$invoice_update = true;
				$refundTransactionID = $response[6];
			}
			add_output('Status : '.$status[$response['0']].' <br>Status Text: '.$response['3']);
			myadmin_log('admin', 'info', json_encode($response), __LINE__, __FILE__);
			if ($status[$response['0']] == 'Declined' || $status[$response['0']] == 'Error') {
				add_output('<br><br>Initializing Void Transaction<br>');
				$response_new = $cc_obj->voidTransaction($transact_ID, $cc_num, $cust_id);
				add_output('Status : '.$status[$response_new['0']].' <br>Status Text: '.$response_new['3']);
				if ($status[$response_new['0']] == 'Approved') {
					$invoice_update = true;
					$refundTransactionID = $response[6] == 0 ? $transact_ID : $response[6];
				}
			}
			$st_txt = $status[$response['0']] == 'Declined' || $status[$response['0']] == 'Error' ? $status[$response_new['0']].'! '.$response_new['3'] : $status[$response['0']].'! '.$response['3'];
			if ($invoice_update) {
				$db = clone $GLOBALS['tf']->db;
				$dbC = clone $GLOBALS['tf']->db;
				$dbD = clone $GLOBALS['tf']->db;
				$dbU = clone $GLOBALS['tf']->db;
				$inv = $invoice_id;
				if ($GLOBALS['tf']->variables->request['refund_amount_opt'] == 'Full') {
					$invoices = explode(',', $invoice_id);
				} else {
					$invoices = [$invoiceId];
				}
				myadmin_log('admin', 'info', json_encode($invoices), __LINE__, __FILE__);
				$invoice = new \MyAdmin\Orm\Invoice($db);
				$now = mysql_now();
				foreach ($invoices as $inv) {
					myadmin_log('admin', 'debug', "SELECT * FROM invoices WHERE invoices_id = {$inv}", __LINE__, __FILE__);
					$dbC->query("SELECT * FROM invoices WHERE invoices_id = {$inv}");
					myadmin_log('admin', 'debug', "Payment Invoice record ".$dbC->num_rows(), __LINE__, __FILE__);
					if ($dbC->num_rows() > 0) {
						
						$dbC->next_record(MYSQL_ASSOC);
						$dbD->query("SELECT * FROM invoices WHERE invoices_id = {$dbC->Record['invoices_extra']}");
						$dbD->next_record(MYSQL_ASSOC);
						$invCharge = $dbD->Record;
						$updateInv = $dbC->Record;
						$invUpdateAmount = bcsub($updateInv['invoices_amount'], $amount, 2);
						if ($GLOBALS['tf']->variables->request['refund_opt'] == 'API' || $GLOBALS['tf']->variables->request['refund_opt'] == 'APISCIU') {
							$dbU->query("UPDATE invoices SET invoices_amount={$invUpdateAmount} WHERE invoices_id = $inv");
							$invoice->setDescription("REFUND: {$updateInv['invoices_description']}")
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
						}
						if ($GLOBALS['tf']->variables->request['refund_opt'] == 'APISCIU') {
							$dbU->query("UPDATE invoices SET invoices_paid = 0 WHERE invoices_id = {$invCharge['invoices_id']}");
						}
						if ($GLOBALS['tf']->variables->request['refund_opt'] == 'DPIDCI') {
							$dbU->query("UPDATE invoices SET invoices_amount={$invUpdateAmount},invoices_deleted=1 WHERE invoices_id = {$updateInv['invoices_id']}");
							$invoice->setDescription("REFUND: {$updateInv['invoices_description']}")
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
							$dbU->query("UPDATE invoices SET invoices_paid = 0,invoices_deleted=1 WHERE invoices_id = {$invCharge['invoices_id']}");
						}
					}
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
					'history_old_value' => "Invoice Amount {$invoiceAmount}"
				]), __LINE__, __FILE__);
			}
			$GLOBALS['tf']->redirect($GLOBALS['tf']->link('index.php', 'choice=none.view_cc_transaction&transaction='.$refundTransactionID.'&module='.$GLOBALS['tf']->variables->request['module'].'&st_txt='.$st_txt));
		}
	}
}
