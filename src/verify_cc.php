<?php

/**
 * @param $cc
 * @param $data
 * @return array
 */
function verify_cc_charge($cc, $data)
{
	$tf = $GLOBALS['tf'];
	$return = [
		'status' => '',
		'text' => ''
	];
	if (!isset($cc['cc'])) {
		$return['status'] = 'error';
		$return['text'] = 'No CC set/present';
		return $return;
	}
	$cc_decrypted = $tf->decrypt($cc['cc']);
	if (!isset($data['cc_amt1_'.$cc_decrypted])) {
		$amt1 = mt_rand(1, 99) / 100;
		$amt2 = mt_rand(1, 99) / 100;
		myadmin_log('billing', 'info', "charging {$data['account_lid']} CC {$cc_decrypted} Amounts {$amt1} and {$amt2}", __LINE__, __FILE__);
		if (!auth_charge_card($data['account_id'], $cc_decrypted, $cc['cc_exp'], $amt1, 'default', 'Validation Random Charge', $cc)
		 || !auth_charge_card($data['account_id'], $cc_decrypted, $cc['cc_exp'], $amt2, 'default', 'Validation Random Charge', $cc)) {
			$return['status'] = 'error';
			$return['text'] = 'There was a problem with this credit card, check the cards available amount and try again.';
		} else {
			$tf->accounts->update(
				$data['account_id'],
				[
				'cc_amt1_'.$cc_decrypted => $amt1,
				'cc_amt2_'.$cc_decrypted => $amt2
													 ]
			);
			$return['status'] = 'ok';
			$return['text'] = 'Successfully Charged Card';
		}
	} else {
		$return['status'] = 'warning';
		$return['text'] = 'Already charged an amount';
	}
	return $return;
}

/**
 * @param $cc
 * @param $data
 * @return array
 */
function verify_cc($cc, $data)
{
	$tf = $GLOBALS['tf'];
	$return = [
		'status' => '',
		'text' => ''
	];
	if (!isset($cc['cc'])) {
		$return['status'] = 'error';
		$return['text'] = 'No CC set/present';
		return $return;
	}
	$cc_decrypted = $tf->decrypt($cc['cc']);
	$request = $tf->variables->request;
	myadmin_log('billing', 'info', "Verify CC Passed {$request['cc_amount1']} and {$request['cc_amount2']} vs. Our  {$data['cc_amt1_'.$cc_decrypted]} and {$data['cc_amt2_'.$cc_decrypted]}", __LINE__, __FILE__);
	if (
		(abs($request['cc_amount1'] - $data['cc_amt1_'.$cc_decrypted]) < 0.06 && abs($request['cc_amount2'] - $data['cc_amt2_'.$cc_decrypted]) < 0.06) ||
		(abs($request['cc_amount1'] - $data['cc_amt2_'.$cc_decrypted]) < 0.06 && abs($request['cc_amount2'] - $data['cc_amt1_'.$cc_decrypted]) < 0.06) ||
		(abs($request['cc_amount1'] - (100 * $data['cc_amt1_'.$cc_decrypted])) < 6 && abs($request['cc_amount2'] - (100 * $data['cc_amt2_'.$cc_decrypted])) < 6) ||
		(abs($request['cc_amount1'] - (100 * $data['cc_amt2_'.$cc_decrypted])) < 6 && abs($request['cc_amount2'] - (100 * $data['cc_amt1_'.$cc_decrypted])) < 6)) {
		$return['status'] = 'ok';
		$return['text'] = 'The Values matched!';
		$tf->accounts->update($data['account_id'], [
			'payment_method' => 'cc',
			'cc' => $cc['cc'],
			'cc_exp' => $cc['cc_exp'],
			'cc_auth_'.$cc_decrypted => 1,
			'disable_cc' => 0,
		]);
	} else {
		$tf->accounts->update($data['account_id'], ['cc_fails_'.$cc_decrypted => isset($data['cc_fails_'.$cc_decrypted]) ? 1 + $data['cc_fails_'.$cc_decrypted] : 1]);
		$return['text'] = 'Verification Failed. The values you have entered did not match the charged amounts. Please verify the values and try again. Only a limited amount of attempts is allowed before the account is locked. Please contact support if you need assistance.';
		$return['status'] = 'failed';
	}
	return $return;
}
