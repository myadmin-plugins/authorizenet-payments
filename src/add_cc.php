<?php
/**
 * Billing Related Services
 * @author Joe Huss <detain@interserver.net>
 * @copyright 2019
 * @package MyAdmin
 * @category Billing
 */

/**
 * Updates the account data with new cc info
 *
 * @param array $cc array data for the cc to add
 * @param array $ccs the ccs array
 * @param array $data client data array
 * @param array $new_data new data array
 * @param string $prefix prefix for request variables
 * @param bool $force optoinally enable force adding circumventing can_use_cc check
 * @return void
 */
function add_cc_new_data($cc, $ccs, $data, $new_data, $prefix, $force = false)
{
	$tf = $GLOBALS['tf'];
	$remove_key = false;
	if ($force === true || can_use_cc($data, $tf->variables->request, false, $prefix.'cc')) {
		if (isset($data['disable_cc']) && $data['disable_cc'] == 1) {
			$remove_key = true;
		}
		//if (!isset($data['cc']) || $data['cc'] == '' || $tf->decrypt($data['cc']) == '' || sizeof($ccs) == 1) {
		$new_data['payment_method'] = 'cc';
		$new_data['cc'] = $cc['cc'];
		$new_data['cc_exp'] = $cc['cc_exp'];
		foreach (['name', 'address', 'city', 'state', 'zip', 'country'] as $field) {
			if (isset($cc[$field]) && $cc[$field] != '') {
				$new_data[$field] = $cc[$field];
			}
		}
		$new_data['ccs'] = myadmin_stringify($ccs, 'json');
	//} else
			//$new_data['ccs'] = myadmin_stringify($ccs, 'json');
	} else {
		$new_data['ccs'] = myadmin_stringify($ccs, 'json');
	}
	$tf->accounts->update($data['account_id'], $new_data);
}

/**
 * adds a creditcard into the clients info
 *
 * @param array  $data
 * @param string $prefix
 * @param bool   $force
 * @return array
 * @throws \Exception
 * @parram bool $force
 */
function add_cc($data, $prefix = '', $force = false)
{
	$tf = $GLOBALS['tf'];
	$minimum_days = 30;
	$max_early_ccs = 4;
	$return = [
		'idx' => '',
		'status' => '',
		'text' => '',
		'data' => $data
	];
	function_requirements('parse_ccs');
	$ccs = parse_ccs($data);
	$signupdays = get_signup_days($minimum_days);
	if (isset($data['ccs_added'])) {
		$ccs_added = $data['ccs_added'];
	} else {
		$ccs_added = count($ccs);
	}
	if ($force !== true && $tf->ima != 'admin' && $signupdays < $minimum_days && (!isset($data['cc_whitelist']) || $data['cc_whitelist'] != 1) && $ccs_added >= $max_early_ccs) {
		$return['status'] = 'error';
		$return['text'] = "New Accounts (those under {$minimum_days} old) are limited to {$max_early_ccs} Credit-Cards until they have reached the {$minimum_days} days.";
		return $return;
	}
	function_requirements('valid_cc');
	if (!valid_cc(trim(str_replace([' ', '_', '-'], ['', '', ''], $tf->variables->request[$prefix.'cc'])))) {
		$return['status'] = 'error';
		$return['text'] = "Invalid card format.";
		return $return;
	}
	$new_data = [];
	if (preg_match('/^[0-9][0-9][0-9][0-9]$/', $tf->variables->request[$prefix.'cc_exp'])) {
		$tf->variables->request[$prefix.'cc_exp'] = mb_substr($tf->variables->request[$prefix.'cc_exp'], 0, 2).'/20'.mb_substr($tf->variables->request[$prefix.'cc_exp'], 2);
	}
	$cc = [
		'cc' => $tf->encrypt(trim(str_replace([' ', '_', '-'], ['', '', ''], $tf->variables->request[$prefix.'cc']))),
		'cc_exp' => trim(str_replace([' ', '_', '-'], ['', '', ''], $tf->variables->request[$prefix.'cc_exp']))
	];
	foreach (['name', 'address', 'city', 'state', 'zip', 'country'] as $field) {
		if (isset($tf->variables->request[$prefix.$field]) && $tf->variables->request[$prefix.$field] != '') {
			$cc[$field] = $tf->variables->request[$prefix.$field];
			if (!isset($data[$field]) && !isset($new_data[$field])) {
				$new_data[$field] = $tf->variables->request[$prefix.$field];
			}
		}
	}
	$ccs[] = $cc;
	$cc_keys = array_keys($ccs);
	$idx = array_pop($cc_keys);
	$ccs_added++;
	$new_data['ccs_added'] = $ccs_added;
	$data['ccs_added'] = $ccs_added;
	add_cc_new_data($cc, $ccs, $data, $new_data, $prefix, $force);
	if (!isset($data['maxmind_riskscore'])) {
		myadmin_log('billing', 'info', 'Calling Update_maxmind()', __LINE__, __FILE__);
		function_requirements('update_maxmind'); // This handles fraud protection
		update_maxmind($data['account_id']);
	}
	if (!isset($data['fraudrecord_score'])) {
		myadmin_log('billing', 'info', 'Calling Update_fraudrecord()', __LINE__, __FILE__);
		function_requirements('update_fraudrecord');
		update_fraudrecord($data['account_id']);
	}
	$data = $tf->accounts->read($data['account_id']);
	$return['idx'] = $idx;
	if (can_use_cc($data, $tf->variables->request, false, $prefix.'cc')) {
		$return['status'] = 'ok';
		$return['text'] = $tf->link('index.php', 'choice=none.manage_payment_types');
	} else {
		$return['status'] = 'verify';
		$return['text'] = $tf->link('index.php', 'choice=none.manage_payment_types&action=verify&idx='.$idx);
	}
	$return['data'] = $data;
	return $return;
}
