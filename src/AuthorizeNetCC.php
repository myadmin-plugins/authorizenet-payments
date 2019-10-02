<?php
/**
* Class contains credit card functions
*/
class AuthorizeNetCC
{

	// Initializing authorize.net api login credentials and other details
	private $Auth_Data = [
		'x_Login' => AUTHORIZENET_LOGIN,
		'x_Password' => AUTHORIZENET_PASSWORD,
		'x_Version' => '3.1',
		'x_Delim_Data' => 'TRUE',
		'x_Encap_Char' => '"'
	];

	/**
	 * refunds a transaction
	 *
	 * @param $cc_num (credit card number 16digits or last four digits)
	 * @param $trans_id (16 digit Transaction Id)
	 * @param $amount (Should be less than or equal to original transaction amount)
	 * @param $custid
	 * @return array|string
	 * @description To refund Credit Card transactions which are less than 120 days
	 */
	public function refund($cc_num, $trans_id, $amount, $custid)
	{
		if (!$cc_num) {
			return 'Error! Credit card Number is empty';
		}
		if (!$trans_id) {
			return 'Error! Transaction id is empty';
		}
		if (!$amount) {
			return 'Error! Amount is empty';
		}
		$this->Auth_Data['x_type'] = 'CREDIT';
		$this->Auth_Data['x_trans_id'] = $trans_id;
		$this->Auth_Data['x_card_num'] = $cc_num;
		$this->Auth_Data['x_exp_date'] = '';
		$this->Auth_Data['x_amount'] = $amount;
		$this->Auth_Data['x_description'] = 'Refund Credit Card Payment';
		$options = [
			CURLOPT_REFERER => 'https://admin.trouble-free.net/',
			CURLOPT_SSL_VERIFYPEER => false, // whether or not to validate the ssl cert of the peer
			// 'CURLOPT_CAINFO' => '/usr/share/curl/curl-ca-bundle.crt', // this option really is only useful if CURLOIPT_SSL_VERIFYPEER is TRUE
		];
		myadmin_log('billing', 'info', "CC Refund - Initializing with cc num {$cc_num} Transaction id {$trans_id} and Amount {$amount}", __LINE__, __FILE__);
		$cc_response = getcurlpage('https://secure.authorize.net/gateway/transact.dll', $this->Auth_Data, $options);
		$tresponse = str_getcsv($cc_response);
		$cc_log = [
			'cc_id' => null,
			'cc_custid' => $custid,
			'cc_timestamp' => mysql_now()
		];
		$rargs = $this->Auth_Data;
		unset($rargs['x_Login'], $rargs['x_Password'], $rargs['x_Delim_Data'], $rargs['x_Encap_Char']);
		if (!empty($rargs)) {
			foreach ($rargs as $field => $value) {
				if (mb_substr(strtolower($field), 2) == 'trans_id') {
					$cc_log['cc_result_'.mb_substr(strtolower($field), 2)] = $value;
				} else {
					$cc_log['cc_request_'.mb_substr(strtolower($field), 2)] = $value;
				}
			}
		}
		$fields = [
			'code', 'subcode', 'reason_code', 'reason_text', 'auth_code', 'avs_code', 'trans_id', 'invoice_num', 'description', 'amount',
			'method', 'trans_type', 'customer_id', 'first_name', 'last_name', 'company', 'address', 'city', 'state', 'zip',
			'country', 'phone', 'fax', 'email', 'shipto_last_name', 'shipto_first_name', 'shipto_company', 'shipto_address', 'shipto_city', 'shipto_state',
			'shipto_zip', 'shipto_country', 'tax', 'duty', 'freight', 'tax_exempt', 'purchase_order_num', 'md5', 'card_code', 'card_verification',
			'', '', '', '', '', '', '', '', '', '',
			'account_num', 'card_type', '', '', '', '', '', '', '', '',
			'', '', '', '', '', '', '', ''
		];
		foreach ($tresponse as $idx => $value) {
			if (isset($fields[$idx]) && $fields[$idx] != '') {
				$response[$fields[$idx]] = $value;
				if ($value != '') {
					$cc_log['cc_result_'.$fields[$idx]] = $value;
				}
			}
		}
		if ($tresponse['0'] == 1) {
			$db = clone $GLOBALS['tf']->db;
			$db->query(make_insert_query('cc_log', $cc_log), __LINE__, __FILE__);
		}

		//request_log($module, $custid, __FUNCTION__, 'authorizenet', 'auth_only', $rargs, $response);
		myadmin_log('billing', 'info', 'CC Refund - Completed values returned Response :'.json_encode($cc_log), __LINE__, __FILE__);
		return $tresponse;
	}

	/**
	 * void transaction
	 *
	 * @param $trans_id (16 digit Transaction Id)
	 * @param $cc_num (credit card number 16digits or last four digits)
	 * @param $custid
	 * @return array|string
	 * @description To void Credit Card transactions
	 */
	public function voidTransaction($trans_id, $cc_num, $custid)
	{
		if (!$cc_num) {
			return 'Error! Credit card Number is empty';
		}
		if (!$trans_id) {
			return 'Error! Transaction id is empty';
		}

		$this->Auth_Data['x_type'] = 'Void';
		$this->Auth_Data['x_trans_id'] = $trans_id;
		$this->Auth_Data['x_card_num'] = $cc_num;
		$this->Auth_Data['x_description'] = 'Void Transaction';
		$options = [
			CURLOPT_REFERER => 'https://admin.trouble-free.net/',
			CURLOPT_SSL_VERIFYPEER => false, // whether or not to validate the ssl cert of the peer
			// 'CURLOPT_CAINFO' => '/usr/share/curl/curl-ca-bundle.crt', // this option really is only useful if CURLOIPT_SSL_VERIFYPEER is TRUE
		];
		myadmin_log('billing', 'info', "Void Transaction - Initializing with cc num {$cc_num} Transaction id {$trans_id}", __LINE__, __FILE__);
		$cc_response = getcurlpage('https://secure.authorize.net/gateway/transact.dll', $this->Auth_Data, $options);
		$tresponse = str_getcsv($cc_response);
		$cc_log = [
			'cc_id' => null,
			'cc_custid' => $custid,
			'cc_timestamp' => mysql_now()
		];
		$rargs = $this->Auth_Data;
		unset($rargs['x_Login'], $rargs['x_Password'], $rargs['x_Delim_Data'], $rargs['x_Encap_Char']);
		if (!empty($rargs)) {
			foreach ($rargs as $field => $value) {
				$cc_log['cc_request_'.mb_substr(strtolower($field), 2)] = $value;
			}
		}
		$fields = ['code', 'subcode', 'reason_code', 'reason_text', 'auth_code', 'avs_code', 'trans_id', 'invoice_num', 'description', 'amount', 'method', 'trans_type', 'customer_id', 'first_name', 'last_name', 'company', 'address', 'city', 'state', 'zip', 'country', 'phone', 'fax', 'email', 'shipto_last_name', 'shipto_first_name', 'shipto_company', 'shipto_address', 'shipto_city', 'shipto_state', 'shipto_zip', 'shipto_country', 'tax', 'duty', 'freight', 'tax_exempt', 'purchase_order_num', 'md5', 'card_code', 'card_verification', '', '', '', '', '', '', '', '', '', '', 'account_num', 'card_type', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''];
		foreach ($tresponse as $idx => $value) {
			if ($fields[$idx] != '') {
				$response[$fields[$idx]] = $value;
				if ($value != '') {
					$cc_log['cc_result_'.$fields[$idx]] = $value;
				}
			}
		}
		if (isset($cc_log['cc_request_trans_id'])) {
			$cc_log['cc_result_trans_id'] = $cc_log['cc_request_trans_id'];
			unset($cc_log['cc_request_trans_id']);
		}
		if ($tresponse['0'] == 1) {
			$db = clone $GLOBALS['tf']->db;
			$db->query(make_insert_query('cc_log', $cc_log), __LINE__, __FILE__);
		}

		//request_log($module, $custid, __FUNCTION__, 'authorizenet', 'auth_only', $rargs, $response);
		myadmin_log('billing', 'info', 'Void Transaction - Completed values returned Response :'.json_encode($cc_log), __LINE__, __FILE__, $module);
		return $tresponse;
	}
}
