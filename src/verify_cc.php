<?php

/**
 * @param $cc
 * @param $data
 * @return array
 */
function verify_cc_charge($cc, $data)
{
    $return = [
        'status' => '',
        'text' => ''
    ];
    if (!isset($cc['cc'])) {
        $return['status'] = 'error';
        $return['text'] = 'No CC set/present';
        return $return;
    }
    $cc_decrypted = \MyAdmin\App::decrypt($cc['cc']);
    // 🔴 This gate is why the account-wide Redis lock exists. It reads state that may
    // have been written on a different cluster member (consistency is EVENTUAL), so two
    // concurrent requests could both pass it and place two pairs of micro-charges.
    // Callers must hold CcVerifyLock for the duration of this step.
    if (!\MyAdmin\Billing\CcMeta::has($data, $cc, 'amt1')) {
        $amt1 = mt_rand(1, 99) / 100;
        $amt2 = mt_rand(1, 99) / 100;
        myadmin_log('billing', 'info', "charging {$data['account_lid']} CC {$cc_decrypted} Amounts {$amt1} and {$amt2}", __LINE__, __FILE__);
        if (!auth_charge_card($data['account_id'], $cc_decrypted, $cc['cc_exp'], $amt1, 'default', 'Validation Random Charge', $cc)
         || !auth_charge_card($data['account_id'], $cc_decrypted, $cc['cc_exp'], $amt2, 'default', 'Validation Random Charge', $cc)) {
            $return['status'] = 'error';
            $return['text'] = 'There was a problem with this credit card, check the cards available amount and try again.';
        } else {
            \MyAdmin\Billing\CcMeta::set($data['account_id'], $cc, ['amt1' => $amt1, 'amt2' => $amt2]);
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
    $return = [
        'status' => '',
        'text' => ''
    ];
    if (!isset($cc['cc'])) {
        $return['status'] = 'error';
        $return['text'] = 'No CC set/present';
        return $return;
    }
    $cc_decrypted = \MyAdmin\App::decrypt($cc['cc']);
    $request = \MyAdmin\App::variables()->request;
    $ourAmt1 = \MyAdmin\Billing\CcMeta::get($data, $cc, 'amt1');
    $ourAmt2 = \MyAdmin\Billing\CcMeta::get($data, $cc, 'amt2');
    // NOTE: this logs both micro-charge amounts, and :22 above logs the full PAN.
    // plan_ccs.md 7.2 / open question 8 owns masking these; the migration does not.
    myadmin_log('billing', 'info', "Verify CC Passed {$request['cc_amount1']} and {$request['cc_amount2']} vs. Our  {$ourAmt1} and {$ourAmt2}", __LINE__, __FILE__);
    if (!isset($request['cc_amount1']) || !isset($request['cc_amount2']) || trim($request['cc_amount2']) == '' || trim($request['cc_amount1']) == '') {
        $return['text'] = 'Missing or Blank Amount Passed.   One or more of the amounts was blank or not passed.   Please verify the values and try again. Please contact support if you need assistance.';
        $return['status'] = 'failed';
    } elseif (
        (abs(floatval($request['cc_amount1']) - $ourAmt1) < 0.06 && abs(floatval($request['cc_amount2']) - $ourAmt2) < 0.06) ||
        (abs(floatval($request['cc_amount1']) - $ourAmt2) < 0.06 && abs(floatval($request['cc_amount2']) - $ourAmt1) < 0.06) ||
        (abs(floatval($request['cc_amount1']) - (100 * $ourAmt1)) < 6 && abs(floatval($request['cc_amount2']) - (100 * $ourAmt2)) < 6) ||
        (abs(floatval($request['cc_amount1']) - (100 * $ourAmt2)) < 6 && abs(floatval($request['cc_amount2']) - (100 * $ourAmt1)) < 6)) {
        $return['status'] = 'ok';
        $return['text'] = 'The Values matched!';
        \MyAdmin\App::accounts()->update($data['account_id'], [
            'payment_method' => 'cc',
            'cc' => $cc['cc'],
            'cc_exp' => $cc['cc_exp'],
            'disable_cc' => 0,
        ]);
        \MyAdmin\Billing\CcMeta::set($data['account_id'], $cc, ['auth' => 1]);
    } else {
        // 🔴 increment(), not `1 + $data[...]`. The old form computed the new value from
        // an array read at request start, so four parallel wrong-amount submissions all
        // read fails=3 and all wrote 4 -- and the card never reached the `> 3` lock.
        \MyAdmin\Billing\CcMeta::increment($data['account_id'], $cc, 'fails');
        $return['text'] = 'Verification Failed. The values you have entered did not match the charged amounts. Please verify the values and try again. Only a limited amount of attempts is allowed before the account is locked. Please contact support if you need assistance.';
        $return['status'] = 'failed';
    }
    return $return;
}
