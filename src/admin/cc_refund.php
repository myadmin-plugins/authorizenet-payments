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
	if (!isset($GLOBALS['tf']->variables->request['confirmation']) || !verify_csrf('cc_refund')) {
		$table = new TFTable;
		$table->csrf('cc_refund');
		$table->set_title('Confirm Refund');
		$table->set_options('cellpadding=10');
		$table->set_post_location('index.php');
		$table->add_hidden('transact_id', $GLOBALS['tf']->variables->request['transact_id']);
		$table->add_hidden('amount', $GLOBALS['tf']->variables->request['amount']);
		$table->add_hidden('card', $GLOBALS['tf']->variables->request['card']);
		$table->add_hidden('cust_id', $GLOBALS['tf']->variables->request['cust_id']);
		$table->add_hidden('module', $GLOBALS['tf']->variables->request['module']);
		$table->add_hidden('inv', $GLOBALS['tf']->variables->request['inv']);
		$table->add_field('Are you sure want to Refund ?', 'l');
		$table->add_field($table->make_radio('confirmation', 'Yes', 'Yes') . 'Yes' . $table->make_radio('confirmation', 'No', true) . 'No', 'l');
		$table->add_row();
		$table->set_colspan(2);
		$table->add_field($table->make_submit('Confirm'));
		$table->add_row();
		add_output($table->get_table());
	} elseif (isset($GLOBALS['tf']->variables->request['confirmation']) && $GLOBALS['tf']->variables->request['confirmation'] === 'Yes') {
		$continue = true;
		$transact_ID = $GLOBALS['tf']->variables->request['transact_id'];
		if ($continue === true) {
			myadmin_log('admin', 'info', 'Going with CC Refund', __LINE__, __FILE__);
			if (isset($GLOBALS['tf']->variables->request['refund_type'])) {
				$refund_type = $GLOBALS['tf']->variables->request['refund_type'];
			} else {
				$refund_type = 'Full';
			}
			if (isset($GLOBALS['tf']->variables->request['amount'])) {
				$amount = $GLOBALS['tf']->variables->request['amount'];
			}
			if (isset($GLOBALS['tf']->variables->request['card'])) {
				$card = $GLOBALS['tf']->variables->request['card'];
			}
			if (isset($GLOBALS['tf']->variables->request['cust_id'])) {
				$cust_id = $GLOBALS['tf']->variables->request['cust_id'];
			}
			if (isset($GLOBALS['tf']->variables->request['inv'])) {
				$invoice_id = $GLOBALS['tf']->variables->request['inv'];
			}
			$cc_num = mb_substr($card, -4);
			$cc_obj = new AuthorizeNetCC;
			$response = $cc_obj->refund($cc_num, $transact_ID, $amount, $cust_id);
			$status = ['', 'Approved', 'Declined', 'Error', 'Held for review'];
			$invoice_update = false;
			if ($status[$response['0']] == 'Approved') {
				$invoice_update = true;
			}
			add_output('Status : '.$status[$response['0']].' <br>Status Text: '.$response['3']);
			if ($status[$response['0']] == 'Declined' || $status[$response['0']] == 'Error') {
				add_output('<br><br>Initializing Void Transaction<br>');
				$response_new = $cc_obj->voidTransaction($transact_ID, $cc_num, $cust_id);
				add_output('Status : '.$status[$response_new['0']].' <br>Status Text: '.$response_new['3']);
				if ($status[$response_new['0']] == 'Approved') {
					$invoice_update = true;
				}
			}
			$st_txt = $status[$response['0']] == 'Declined' || $status[$response['0']] == 'Error' ? $status[$response_new['0']].'! '.$response_new['3'] : $status[$response['0']].'! '.$response['3'];
			if ($invoice_update) {
				$db = clone $GLOBALS['tf']->db;
				$inv = $db->real_escape($invoice_id);
				$inv_id = (int)$inv;
				if ($inv_id && !empty($db)) {
					$db->query("update invoices set invoices_paid=0, invoices_type=2 where invoices_id = $inv_id");
				}
			}
			$GLOBALS['tf']->redirect($GLOBALS['tf']->link('index.php', 'choice=none.view_cc_transaction&transaction='.$transact_ID.'&module='.$GLOBALS['tf']->variables->request['module'].'&st_txt='.$st_txt));
		}
	}
}
