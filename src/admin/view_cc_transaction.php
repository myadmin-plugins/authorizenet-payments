<?php
/**
* Creditcard Related Functionality
* @author Joe Huss <detain@interserver.net>
* @copyright 2019
* @package MyAdmin
* @category Billing
*/

function view_cc_transaction()
{
	page_title('Credit Card Transaction Information');
	function_requirements('has_acl');
	if ($GLOBALS['tf']->ima != 'admin' || !has_acl('view_customer')) {
		dialog('Not admin', 'Not Admin or you lack the permissions to view this page.');
		return false;
	}
	add_js('bootstrap');
	add_js('font-awesome');
	add_js('isotope');
	$GLOBALS['body_extra'] = ' data-spy="scroll" data-target="#scrollspy" style="position: relative;"';
	$GLOBALS['tf']->add_html_head_css_file(URL_ROOT.'/css/view_paypal_transaction.css');
	$GLOBALS['tf']->add_html_head_js_file(URL_ROOT.'/js/view_paypal_transaction.js');
	//$transaction_types = get_paypal_transaction_types();
	$module = get_module_name((isset($GLOBALS['tf']->variables->request['module']) ? $GLOBALS['tf']->variables->request['module'] : 'default'));
	$db = clone $GLOBALS['tf']->db;
	$db_check_invoice = get_module_db($module);

	if (isset($GLOBALS['tf']->variables->request['transaction'])) {
		$transaction = $db->real_escape($GLOBALS['tf']->variables->request['transaction']);
		//$transaction_id = mb_substr($transaction, 0, 11);
		$query = "select * from cc_log where cc_result_trans_id='{$transaction}'";
	}
	$db->query($query);
	if ($db->num_rows() == 0) {
		$db = get_module_db($module);
		$db->query($query);
	}
	$table = new TFtable;
	$transactions = [];
	$smarty = new TFSmarty;
	if ($db->num_rows() > 0) {
		$transaction = [];
		while ($db->next_record(MYSQL_ASSOC)) {
			foreach ($db->Record as $key => $value) {
				$key = str_replace('cc_', '', $key);
				$key = str_replace('result_', '', $key);
				$key = ucwords(str_replace('_', ' ', $key));
				if ($key == 'Trans Id') {
					$temp_trans_id = $value;
				}
				if ($key == 'Custid') {
					$transaction[$key] = $value;
					//$transaction[$key] = $table->make_link('choice=none.edit_customer&amp;lid='.$value, $value, false, 'target="_blank" title="Edit Customer"');
				} elseif ($key == 'Invoice Num') {
					$db_check_invoice->query("SELECT * FROM invoices WHERE invoices_custid={$db->Record['cc_custid']} and invoices_description='Credit Card Payment {$temp_trans_id}'", __LINE__, __FILE__);
					if ($db_check_invoice->num_rows() > 0) {
						$invoice_arr = [];
						while ($db_check_invoice->next_record(MYSQL_ASSOC)) {
							$invoice_arr[] = $db_check_invoice->Record['invoices_id'];
						}
						$transaction[$key] = implode(',', $invoice_arr);
					}
				} else {
					$transaction[$key] = $value;
				}
			}
			$transactions[] = $transaction;
		}
	}
	$cats = get_cc_cats_and_fields();
	$smarty->assign('transactions', $transactions);
	$smarty->assign('transaction', $transaction);
	$smarty->assign('paypal_cats', $cats);
	$smarty->assign('module', $module);
	if (isset($GLOBALS['tf']->variables->request['st_txt'])) {
		$smarty->assign('st_txt', $GLOBALS['tf']->variables->request['st_txt']);
	}
	$smarty->assign('module', $module);
	add_output($smarty->fetch('billing/payments/view_cc_transaction.tpl'));
}

/**
 * @return array
 */
function get_cc_cats_and_fields()
{
	return [
		[
			'name' => 'Transaction and Notification Information',
			'desc' => 'Transaction and notification-related variables identify the merchant that is receiving a payment or other notification and transaction-specific information.',
			'fields' => [
				'Auth Code' => 'Authorization or approval code.',
				'Avs Code' => "Address Verification Service (AVS) response code.Indicates the result of the AVS filter. \nA = Address (Street) matches, ZIP does not.\nB = Address information not provided for AVS check.\nE = AVS error.\nG = Non-U.S. Card Issuing Bank.\nN = No Match on Address (Street) or ZIP.\nP = AVS not applicable for this transaction.\nR = Retry—System unavailable or timed out.\nS = Service not supported by issuer.\nU = Address information is unavailable.\nW = Nine digit ZIP matches, Address (Street) does not.\nX = Address (Street) and nine digit ZIP match.\nY = Address (Street) and five digit ZIP match.\nZ = Five digit ZIP matches, Address (Street) does not.",
				'Trans Id' => 'The payment gateway assigned identification number for transaction.The transId value must be used for any follow-on transactions such as a credit, prior authorization and capture, or void.',
				'Code' => "The overall status of the transaction\n1—Approved\n2—Declined\n3—Error\n4—Held for Review",
				'Reason Text' => 'Transaction result text.',
				'Trans Type' => 'The kind of transaction'
			]
		],
		[
			'name' => 'Buyer Information',
			'desc' => 'Buyer information identifies the buyer or initiator of a transaction. Additional contact or shipping information may be provided.',
			'fields' => [
				'First Name' => "Customer's first nameLength: \n64 characters",
				'Last Name' => "Customer's last nameLength: \n64 characters",
				'Company' => "Company of customer:\n64 characters",
				'Address' => "Customer's street address.Length:\n200 characters",
				'City' => "City of customer's addressLength:\n40 characters",
				'State' => "State of customer's addressLength:\n40 characters",
				'Zip' => "Zip code of customer's address.Length:\n20 characters",
				'Country' => "ISO 3166 country code associated with customer's\naddressLength: 2 characters",
				'Phone' => "Customer's telephone number.Length:\n20 characters",
				'Fax' => "Customer's fax number.Length:\n20 characters",
				'Email' => "Customer's primary email address. Use this\nemail to provide any credits.Length: 127 characters",
				'Shipto First Name' =>"Shipping customer's first nameLength: \n64 characters",
				'Shipto Last Name' => "Shipping customer's last nameLength: \n64 characters",
				'Shipto Company' => "Shipping to Company of customer:\n64 characters",
				'Shipto Address' => "Shipping to customer's street address.Length:\n200 characters",
				'Shipto City' => "Shipping to city of customer's addressLength:\n40 characters",
				'Shipto State' => " Shipping to state of customer's addressLength:\n40 characters",
				'Shipto Zip' => " Shipping to zip code of customer's address.Length:\n20 characters",
				'Shipto Country' => "Shipping to ISO 3166 country code associated with customer's\naddressLength: 2 characters"
			]
		],
		[
			'name' => 'Payment Information',
			'desc' => 'Payment information identifies the amount and status of a payment transaction, including fees.',
			'fields' => [
				'Invoice Num' => 'Merchant-defined invoice number associated with the order.',
				'Description' => "Description of the item purchased.\nString: 255-character maximum.",
				'Amount' => 'Authorization amount',
				'Method' => 'Payment method.',
				'Tax' => '',
				'Duty' => '',
				'Freight' => '',
				'Tax Exempt' => '',
				'Purchase Order Num' => "The merchant-assigned purchase order number.
Purchase order number must be created dynamically on the merchant's server or provided on a per-transaction basis. The payment gateway does not perform this function.",
				'Md5' => 'Payment gateway-generated MD5 hash value that can be used to authenticate the transaction response.
Because transaction responses are returned using an SSL connection, this feature is not necessary for AIM. ',
				'Card Code' => "Card code verification (CCV) response code. Indicates result of the CCV filter.\nM = Match.\nN = No Match.\nP = Not Processed.\nS = Should have been present.\nU = Issuer unable to process request.",
				'Card Verification' => "Cardholder authentication verification response code.\nBlank or not present = CAVV not validated.
\n0 = CAVV not validated because erroneous data was submitted.
\n1 = CAVV failed validation.
\n2 = CAVV passed validation.
\n3 = CAVV validation could not be performed; issuer attempt incomplete.
\n4 = CAVV validation could not be performed; issuer system error.
\n5 = Reserved for future use.
\n6 = Reserved for future use.
\n7 = CAVV attempt—failed validation—issuer available (U.S.-issued card/non-U.S acquirer).
\n8 = CAVV attempt—passed validation—issuer available (U.S.-issued card/non-U.S. acquirer).
\n9 = CAVV attempt—failed validation—issuer unavailable (U.S.-issued card/non-U.S. acquirer).
\nA = CAVV attempt—passed validation—issuer unavailable (U.S.-issued card/non-U.S. acquirer).
\nB = CAVV passed validation, information only, no liability shift.",
				'Account Num' => 'The last four digits of either the card number or bank account number used for the transaction in the format XXXX1234.',
				'Card Type' => '',
				'Customer Id' =>''
			]
		],
		[
			'name' => 'Misc Information',
			'desc' => 'Assorted information that doesnt really fit anywher else.',
			'fields' => [
				'Id',
				'Custid',
				'Lid',
				'Timestamp'
			]
		]
	];
}
