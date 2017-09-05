<?php

/**
 * Returns a list of all the fields in an authorize.net csv response
 * with name and description and the idx of each element matches
 *
 * @param $result
 * @param $fields
 * @return array an array of  idx => name/description fields for authorize.net
 */
function map_authorizenet_fields($result, $fields) {
	$parts = explode(',', $result['data']);
	if (preg_match("/^(?P<code>[1-4]),(?P<subcode>[^,]*),(?P<reason_code>\d*),(?P<reason_text>[^,]*),(?P<auth_code>\w{0,6}),(?P<avs_code>[ABEGNPRSUWXYZ]?),(?P<trans_id>\d+),(?P<invoice_num>\w{0,20}),(?P<description>.*),(?P<amount>[0-9\.-]{1,15}),(?P<method>ECHECK|CC),(?P<trans_type>AUTH_CAPTURE|AUTH_ONLY|CAPTURE_ONLY|CREDIT|PRIOR_AUTH_CAPTUREVOID),(?P<customer_id>\w{0,20}),(?P<first_name>[^,]{0,50}),(?P<last_name>[^,]{0,50}),(?P<company>[^,]{0,50}),(?P<address>[^,]{0,60}),(?P<city>[^,]{0,40}),(?P<state>[^,]{0,40}),(?P<zip>[^,]{0,20}),(?P<country>[^,]{0,60}),(?P<phone>[^,]{0,25}),(?P<fax>[^,]{0,25}),(?P<email>[^,]{0,255}),(?P<shipto_first_name>[^,]{0,50}),(?P<shipto_last_name>[^,]{0,50}),(?P<shipto_company>[^,]{0,50}),(?P<shipto_address>[^,]{0,60}),(?P<shipto_city>[^,]{0,40}),(?P<shipto_state>[^,]{0,40}),(?P<shipto_zip>[^,]{0,20}),(?P<shipto_country>[^,]{0,60}),(?P<tax>\d*),(?P<duty>\d*),(?P<freight>[0-9\.]*),(?P<tax_exempt>|TRUE|FALSE|T|F|YES|NO|Y|N|1|0),(?P<purchase_order_num>\w{0,25}),(?P<md5>\w{30,35}),(?P<card_code>|M|N|P|S|U),(?P<card_verification>[0-9,A,B]?),,,,,,,,,,,(?P<account_num>X*\d{4}),(?P<card_type>|Visa|MasterCard|American Express|Discover|Diners Club|JCB),,,,,,,,,,,,,,,,/iU", $result['data'], $matches)) {
		for ($x = 0; $x <= 42; $x++)
			unset($matches[$x]);
		foreach ($matches as $key => $value) {
			$field = "cc_result_{$key}";
			/*
			$comment = ucwords(str_replace(array('cc', '_'), array('CC Charge', ' '), $field));
			if (!isset($GLOBALS['finished_'.$field]))
				echo "ALTER TABLE cc_log ADD COLUMN {$field} VARCHAR(255) NOT NULL COMMENT '{$comment}';\n";
			$GLOBALS['finished_'.$field] = 1;
			*/
			$result[$field] = $value;
		}
		unset($result['data']);
		return $result;
	}
	if (preg_match("/^(?P<code>[1-4]),(?P<subcode>[^,]*),(?P<reason_code>\d*),(?P<reason_text>[^,]*),(?P<auth_code>\w{0,6}),(?P<avs_code>[ABEGNPRSUWXYZ]?),(?P<trans_id>\d+),/iU", $result['data'], $matches)) {
		for ($x = 0, $xMax = count($parts); $x <= $xMax; $x++)
			unset($matches[$x]);
		foreach ($matches as $key => $value)
			$result['cc_result_'.$key] = $value;
		unset($result['data']);
		return $result;
	}
		$parts = explode(',', $result['data']);
		//if (sizeof($parts) > 52) myadmin_log('billing', 'info', "Somehow we got " . sizeof($parts) . " Parts in this CSV response, expecting <52", __LINE__, __FILE__);
		$problems = 0;
		foreach ($parts as $idx => $field_data) {
			if ($field_data != '') {
				if (isset($fields[$idx])) {
					if (!isset($fields[$idx]['name']))
						print_r($fields[$idx]);
					$field_name = strtolower(str_replace([' '], ['_'], $fields[$idx]['name']));
					$result[$field_name] = $field_data;
				} else {
					$problems++;
					myadmin_log('billing', 'info', "{$field_data} index '{$idx}' out of range", __LINE__, __FILE__);
				}
			}
		}
		if ($problems == 0)
			unset($result['data']);
		return $result;
}
