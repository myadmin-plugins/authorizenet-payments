<?php

function manage_cc()
{
    add_js('bootstrap');
    add_js('font-awesome');
    add_js('card.jquery');
    add_js('card');
    page_title('Manage Credit Cards');
    $data = \MyAdmin\App::accounts()->data;
    if (!isset($data['ccs'])) {
        $ccs = [];
    } else {
        $ccs = myadmin_unstringify($data['ccs']);
    }
    if (count($ccs) == 0 && isset($data['cc']) && $data['cc'] != '') {
        $ccs[] = ['cc' => \MyAdmin\App::decrypt($data['cc']), 'cc_exp' => $data['cc_exp']];
    }
    if (isset(\MyAdmin\App::variables()->request['action'])) {
        $action = \MyAdmin\App::variables()->request['action'];
    } else {
        $action = 'default';
    }

    $orig_url = (!isset(\MyAdmin\App::variables()->request['orig_url']) ? $_SERVER['REQUEST_URI'] : \MyAdmin\App::variables()->request['orig_url']);
    $orig_data = $data;
    switch ($action) {
        case 'verify':
            $idx = (int)\MyAdmin\App::variables()->request['idx'];
            if (!isset($ccs[$idx])) {
                add_output('Invalid CC Passed');
                break;
            }
            $cc = $ccs[$idx];
            $get_amount = false;
            if (\MyAdmin\Billing\CcMeta::isLocked($data, $cc)) {
                add_output('Reached the max number of tries to authenticate this card');
            } else {
                $table = new TFTable();
                $table->hide_table();
                $table->hide_title();
                $table->set_method('GET');
                $table->add_hidden('orig_url', htmlspecial($orig_url));
                $table->add_hidden('idx', $idx);
                $table->add_hidden('action', 'verify');

                //myadmin_log('billing', 'info', json_encode($data), __LINE__, __FILE__);
                myadmin_log('billing', 'info', 'Checking CC '.\MyAdmin\App::decrypt($cc['cc']), __LINE__, __FILE__);
                if ((!isset(\MyAdmin\App::variables()->request['terms']) && !\MyAdmin\Billing\CcMeta::has($data, $cc, 'amt1')) || !verify_csrf('manage_cc_verify')) {
                    add_output('<b>Credit Card Verification</b><br>');
                    $table->csrf('manage_cc_verify');
                    $table->add_field(
                        'Verification is needed before your credit card is available for use. Interserver will charge your credit card with two amounts under $1.00 each. Login to your credit cards online banking or call your bank to verify the two amounts charged. They often show under “Pending Charges” with your bank.  These authorization charges will disappear in about 3-5 days depending on your bank.',
                        'l'
                    );
                    $table->add_row();
                    $table->add_field('Card Security Code');
                    $table->add_row();
                    $table->add_field($table->make_input('cc_ccv2'));
                    $table->add_row();
                    $table->add_field('The Card Security Code is the three or four digit value printed on your card.');
                    $table->add_row();
                    $table->add_field($table->make_checkbox('terms', 1, false).'I accept two temporary charges under $1.00 each.');
                    $table->add_row();
                    $table->add_field('<input type=submit value="Send Authorization" class="ui-button ui-widget ui-state-hover ui-corner-all" style="color: white; padding: 0.1em 0.5em; background: none repeat-x scroll 50% 50% #004ccc; border-radius: 10px;">' .
                        '<a href="'.\MyAdmin\App::link('index.php', 'choice=none.manage_cc&action=default&orig_url='.htmlspecial($orig_url)) .
                        '" style="padding: 0.1em 0.5em; border-radius: 10px;" class="ui-button ui-widget ui-state-hover ui-corner-all">Cancel</a>');
                    $table->add_row();
                    add_output($table->get_table().'<br>');
                } elseif (!isset(\MyAdmin\App::variables()->request['cc_amount1'])) {
                    if (!\MyAdmin\Billing\CcMeta::has($data, $cc, 'amt1')) {
                        $amt1 = mt_rand(1, 99) / 100;
                        $amt2 = mt_rand(1, 99) / 100;
                        myadmin_log('billing', 'info', "Amt1 $amt1  Amt2 $amt2", __LINE__, __FILE__);
                        if (!auth_charge_card(\MyAdmin\App::session()->account_id, \MyAdmin\App::decrypt($cc['cc']), $cc['cc_exp'], $amt1, 'default', 'Validation Random Charge', $cc) || !auth_charge_card(\MyAdmin\App::session()->account_id, \MyAdmin\App::decrypt($cc['cc']), $cc['cc_exp'], $amt2, 'default', 'Validation Random Charge', $cc)) {
                            add_output('There was a problem with this credit card, check the cards available amount and try again.');
                        } else {
                            \MyAdmin\Billing\CcMeta::set(
                                \MyAdmin\App::session()->account_id,
                                $cc,
                                ['amt1' => $amt1, 'amt2' => $amt2]
                            );
                            $get_amount = true;
                        }
                    } else {
                        $get_amount = true;
                    }
                } else {
                    myadmin_log('billing', 'info', 'Passed '.\MyAdmin\App::variables()->request['cc_amount1'].' '.\MyAdmin\App::variables()->request['cc_amount2'], __LINE__, __FILE__);
                    $ourAmt1 = \MyAdmin\Billing\CcMeta::get($data, $cc, 'amt1');
                    $ourAmt2 = \MyAdmin\Billing\CcMeta::get($data, $cc, 'amt2');
                    // NOTE: logs both micro-charge amounts. plan_ccs.md 7.2 owns masking.
                    myadmin_log('billing', 'info', 'Ours '.$ourAmt1.' '.$ourAmt2, __LINE__, __FILE__);
                    // 🔴 Tolerance here is 0.02; verify_cc.php uses 0.06 for the same
                    // comparison. That discrepancy is pre-existing and deliberate to leave
                    // alone -- plan_ccs.md 7.4 owns unifying it. Only the reads changed.
                    if ((abs(\MyAdmin\App::variables()->request['cc_amount1'] - $ourAmt1) < 0.02 && abs(\MyAdmin\App::variables()->request['cc_amount2'] - $ourAmt2) < 0.02)
                        || (abs(\MyAdmin\App::variables()->request['cc_amount1'] - $ourAmt2) < 0.02 && abs(\MyAdmin\App::variables()->request['cc_amount2'] - $ourAmt1) < 0.02)
                        || (abs(\MyAdmin\App::variables()->request['cc_amount1'] - (100 * $ourAmt1)) < 2 && abs(\MyAdmin\App::variables()->request['cc_amount2'] - (100 * $ourAmt2)) < 2)
                        || (abs(\MyAdmin\App::variables()->request['cc_amount1'] - (100 * $ourAmt2)) < 2 && abs(\MyAdmin\App::variables()->request['cc_amount2'] - (100 * $ourAmt1)) < 2)) {
                        add_output('The Values matched!');
                        \MyAdmin\App::accounts()->update(
                            \MyAdmin\App::session()->account_id,
                            [
                            'payment_method' => 'cc',
                            'disable_cc' => 0,
                        ]
                        );
                        \MyAdmin\Billing\CcMeta::set(\MyAdmin\App::session()->account_id, $cc, ['auth' => 1]);
                        \MyAdmin\App::output()->redirect(\MyAdmin\App::link('index.php', 'choice=none.manage_cc&orig_url='.htmlspecial($orig_url)));
                    } else {
                        dialog('Verification Failed', 'Verification Failed. The values you have entered did not match the charged amounts. Please verify the values and try again. Only a limited amount of attempts is allowed before the account is locked. Please contact support if you need assistance.');
                        $get_amount = true;
                        // increment(), not `1 + $data[...]`: the old form computed the
                        // new value from a request-start snapshot, so parallel wrong-amount
                        // submissions all read the same value and the card never locked.
                        \MyAdmin\Billing\CcMeta::increment(\MyAdmin\App::session()->account_id, $cc, 'fails');
                    }
                }
            }
            if ($get_amount == true) {
                $table = new TFTable();
                $table->csrf('manage_cc_verify');
                $table->add_hidden('orig_url', htmlspecial($orig_url));
                $table->add_hidden('idx', $idx);
                $table->add_hidden('action', 'verify');
                add_output('<b>Credit Card Verification</b><br>');
                $table->add_field('Verification is needed before your credit card is available for use. Interserver will charge your credit card with two amounts under $1.00 each. Login to your credit cards online banking or call your bank to verify the two amounts charged. They often show under “Pending Charges” with your bank.  These authorization charges will disappear in about 3-5 days depending on your bank.', 'l');
                $table->add_row();
                $table->add_field('Please enter the two amounts charged to your credit card. The values must be in USD');
                $table->add_row();
                $table->add_field('$.'.$table->make_input('cc_amount1', '', 5, 2));
                $table->add_row();
                $table->add_field('$.'.$table->make_input('cc_amount2', '', 5, 2));
                $table->add_row();
                $table->add_field('<input type=submit value="Complete Authorization" class="ui-button ui-widget ui-state-hover ui-corner-all" style="color: white; padding: 0.1em 0.5em; background: none repeat-x scroll 50% 50% #004ccc; border-radius: 10px;">' .
                    '<a href="'.\MyAdmin\App::link('index.php', 'choice=none.manage_cc&action=default&orig_url='.htmlspecial($orig_url)) .
                    '" style="padding: 0.1em 0.5em; border-radius: 10px;" class="ui-button ui-widget ui-state-hover ui-corner-all">Cancel</a>');
                $table->add_row();
                $table->add_field('*Please note that the charges on your credit card statement may be in a different currency from the one required on the last confirmation page described above. If your credit card statement does not display the original transaction amounts in the required currency, you will have to contact your credit card company. They should be able to tell you the amounts of the two charges in the original currency.');
                $table->add_row();
                add_output($table->get_table());
            }
            break;
        case 'primary':
            if (verify_csrf_referrer(__LINE__, __FILE__)) {
                $idx = (int)\MyAdmin\App::variables()->request['idx'];
                \MyAdmin\App::accounts()->update(
                    \MyAdmin\App::session()->account_id,
                    [
                    'payment_method' => 'cc',
                    'cc' => \MyAdmin\App::encrypt(\MyAdmin\App::decrypt($ccs[$idx]['cc'])),
                    'cc_exp' => $ccs[$idx]['cc_exp']
                                                         ]
                );
                // This card is now $data['cc']. account_cc_primary mirrors that field and
                // nothing kept the two in step -- 56 accounts drifted within a day of F2.
                \MyAdmin\Billing\CcMeta::setPrimary((int)\MyAdmin\App::session()->account_id, $ccs[$idx]);
                \MyAdmin\App::output()->redirect(\MyAdmin\App::link('index.php', 'choice=none.manage_cc&orig_url='.htmlspecial($orig_url)));
            }
            break;
        case 'delete':
            if (verify_csrf_referrer(__LINE__, __FILE__)) {
                $idx = (int)\MyAdmin\App::variables()->request['idx'];
                $cc = $ccs[$idx];
                unset($ccs[$idx]);
                // Soft-delete the account_ccs row and clear its primary flag in the same
                // statement. The row is never removed: its account_cc_fails preserves the
                // verification lock across a delete/re-add cycle.
                \MyAdmin\Billing\CcMeta::softDelete(\MyAdmin\App::session()->account_id, $cc);
                $new_data = ['ccs' => myadmin_stringify($ccs, 'json')];
                if (isset($data['cc']) && isset($cc['cc']) && \MyAdmin\App::decrypt($cc['cc']) == \MyAdmin\App::decrypt($data['cc'])) {
                    $new_data['cc'] = '';
                }
                \MyAdmin\App::accounts()->update(\MyAdmin\App::session()->account_id, $new_data);
                \MyAdmin\App::output()->redirect(\MyAdmin\App::link('index.php', 'choice=none.manage_cc&orig_url='.htmlspecial($orig_url)));
            }
            break;
        case 'add':
            $minimum_days = 30;
            $max_early_ccs = 2;
            $signupdays = get_signup_days($minimum_days);
            if (\MyAdmin\App::ima() != 'admin' && $signupdays < $minimum_days && (!isset($data['cc_whitelist']) || $data['cc_whitelist'] != 1) && count($ccs) >= $max_early_ccs) {
                add_output("New Accounts (those under {$minimum_days} old) are limited to {$max_early_ccs} Credit-Cards until they have reached the {$minimum_days} days, your account appears to be {$signupdays} day(s) old.<br>");
                break;
            }
            if (isset(\MyAdmin\App::variables()->request['cc']) && verify_csrf('manage_cc_add')) {
                $new_data = [];
                $cc = [
                    'cc' => \MyAdmin\App::encrypt(trim(str_replace([' ', '_', '-'], ['', '', ''], \MyAdmin\App::variables()->request['cc']))),
                    'cc_exp' => trim(str_replace([' ', '_', '-'], ['', '', ''], \MyAdmin\App::variables()->request['cc_exp']))
                ];
                foreach (['name', 'address', 'address2', 'city', 'state', 'zip', 'country'] as $field) {
                    if (isset(\MyAdmin\App::variables()->request[$field]) && \MyAdmin\App::variables()->request[$field] != '') {
                        $cc[$field] = \MyAdmin\App::variables()->request[$field];
                    }
                    if (!isset($data[$field]) && !isset($new_data[$field]) && isset($cc[$field])) {
                        $new_data[$field] = $cc[$field];
                    }
                }
                $ccs[] = $cc;
                if (can_use_cc($data)) {
                    if (!isset($data['cc']) || $data['cc'] == '' || \MyAdmin\App::decrypt($data['cc']) == '') {
                        $new_data['payment_method'] = 'cc';
                        $new_data['cc'] = \MyAdmin\App::encrypt(\MyAdmin\App::decrypt($cc['cc']));
                        $new_data['cc_exp'] = $cc['cc_exp'];
                        $new_data['ccs'] = myadmin_stringify($ccs, 'json');
                    } else {
                        $new_data['ccs'] = myadmin_stringify($ccs, 'json');
                    }
                } else {
                    $new_data['ccs'] = myadmin_stringify($ccs, 'json');
                }
                \MyAdmin\App::accounts()->update(\MyAdmin\App::session()->account_id, $new_data);
                // plan_ccs.md: this is the SECOND add path -- it builds its own $cc and
                // writes the blob without ever calling add_cc(), so hooking add_cc() alone
                // would have left this one silently not creating account_ccs rows.
                // No-op unless CCMETA_WRITE_TABLE is on; idempotent per live PAN.
                \MyAdmin\Billing\CcMeta::addCard((int)\MyAdmin\App::session()->account_id, $cc);
                if (can_use_cc($data)) {
                    \MyAdmin\App::output()->redirect(\MyAdmin\App::link('index.php', 'choice=none.manage_cc&orig_url='.htmlspecial($orig_url)));
                } else {
                    \MyAdmin\App::output()->redirect(\MyAdmin\App::link('index.php', 'choice=none.manage_cc&orig_url='.htmlspecial($orig_url).'&action=verify&idx='.(count($ccs) - 1)));
                }
            } else {
                $smarty = new TFSmarty();
                $smarty->assign('orig_url', htmlspecial($orig_url));
                $smarty->assign('action', 'add');
                $smarty->assign('csrf_token', get_csrf('manage_cc_add'));
                $smarty->assign('choice', 'none.manage_cc');
                add_output($smarty->fetch('billing/payments/manage_cc_jquery.tpl'));
                //            $table->add_field('<a href="'.\MyAdmin\App::link('index.php', 'choice=none.manage_cc&action=default&orig_url='.htmlspecial($orig_url)).'" style="padding: 0.1em 0.5em; border-radius: 10px;" class="ui-button ui-widget ui-state-hover ui-corner-all">Cancel</a>');
            }
            break;
        case 'default':
        default:
            $payment_settings = false;
            $fields = ['payment_method'];
            $methods = ['paypal'];
            if (can_use_cc($data, false, false)) {
                $fields[] = 'cc_auto';
                $payment_settings = true;
                $methods[] = 'cc';
            }
            $new_data = [];
            foreach ($fields as $field) {
                if (isset(\MyAdmin\App::variables()->request[$field])) {
                    if ($field == 'payment_method' && $payment_settings === false && !in_array(\MyAdmin\App::variables()->request[$field], $methods)) {
                        add_output('Invalid Payment Method Specified');
                    } else {
                        $new_data[$field] = \MyAdmin\App::variables()->request[$field];
                        $data[$field] = \MyAdmin\App::variables()->request[$field];
                    }
                }
            }
            if (isset($data['payment_method']) && !isset($orig_data['payment_method'])) {
                if (!isset(\MyAdmin\App::accounts()->data['maxmind_riskscore'])) {
                    function_requirements('update_maxmind'); // This handles fraud protection
                    update_maxmind(\MyAdmin\App::session()->account_id);
                }
                if (!isset(\MyAdmin\App::accounts()->data['fraudrecord_score'])) {
                    function_requirements('update_fraudrecord');
                    update_fraudrecord(\MyAdmin\App::session()->account_id);
                }
            }
            if (count($new_data) > 0 && verify_csrf('manage_cc')) {
                \MyAdmin\App::accounts()->update(\MyAdmin\App::session()->account_id, $new_data);
            }
            add_output('<div style="width: 1000px; text-align: left;">
<b>Payment Sources</b><br>
Flexibility is important. Thats why you have the ability to set multiple payment sources.<br>
All credit cards must be verified before they can be used.  To verify them click the "Not Verified" link.<br><br>
');
            $table = new TFTable();
            $table->hide_table();
            $table->hide_title();
            $csrf_token = $table->csrf('manage_cc');
            $table->add_hidden('orig_url', htmlspecial($orig_url));
            //$table->set_row_options('style=" border-top: 1px solid gray;"');
            $table->set_col_options('style="min-width: 300px;"');
            $table->add_field('<b>Information</b>', 'l');
            $table->add_field();
            $table->add_field('<b>Expiration Date</b>');
            $table->add_field('<b>Verified</b>');
            $table->add_field();
            $table->add_row();
            foreach ($ccs as $idx => $cc) {
                $cc_hidden = \MyAdmin\App::decrypt($cc['cc']);
                $table->set_col_options('style="border-top: 1px solid gray;"');
                $table->add_field('Ending In '.mb_substr($cc_hidden, mb_strlen($cc_hidden) - 4), 'l');
                $table->set_col_options('style="border-top: 1px solid gray;"');
                if (can_use_cc($data, $cc, false)) {
                    $verified = true;
                } else {
                    $verified = false;
                }
                if (isset($data['cc']) && isset($cc['cc']) && \MyAdmin\App::decrypt($cc['cc']) == \MyAdmin\App::decrypt($data['cc'])) {
                    $table->add_field('<a href="" style="color: white; padding: 0.1em 0.5em; background: none repeat-x scroll 50% 50% #c67605; border-radius: 10px;" class="ui-button ui-widget ui-state-hover ui-corner-all">Primary Billing Source</a>', 'r');
                } else {
                    if ($verified == true) {
                        $table->add_field('<a href="'.\MyAdmin\App::link('index.php', 'choice=none.manage_cc&action=primary&orig_url='.htmlspecial($orig_url).'&idx='.$idx).'" style="padding: 0.1em 0.5em; border-radius: 10px;" class="ui-button ui-widget ui-state-hover ui-corner-all">Make Primary</a>', 'r');
                    } else {
                        $table->add_field();
                    }
                }
                $table->set_col_options('style="border-top: 1px solid gray;"');
                $table->add_field($cc['cc_exp']);
                if ($verified == true) {
                    $table->set_col_options('style="border-top: 1px solid gray;"');
                    $table->add_field('<a href="" style="padding: 0.1em 0.5em; border-radius: 10px;" class="ui-button ui-widget ui-state-hover ui-corner-all">Verified</a>');
                } else {
                    $table->set_col_options('style="border-top: 1px solid gray;"');
                    $table->add_field('<a href="'.\MyAdmin\App::link('index.php', 'choice=none.manage_cc&action=verify&orig_url='.htmlspecial($orig_url) .'&idx='.$idx).'" style="padding: 0.1em 0.5em; border-radius: 10px;" class="ui-button ui-widget ui-state-hover ui-corner-all">Not Verified</a>');
                }
                $table->set_col_options('style="border-top: 1px solid gray;"');
                $table->add_field('<a href="'.\MyAdmin\App::link('index.php', 'choice=none.manage_cc&action=delete&orig_url='.htmlspecial($orig_url).'&idx='.$idx).'" style="color: white; padding: 0.1em 0.5em; background: none repeat-x scroll 50% 50% #cc433c; border-radius: 10px;" class="ui-button ui-widget ui-state-hover ui-corner-all" >Remove</a>');
                $table->add_row();
            }
            if ($payment_settings == true) {
                $table->set_colspan(2);
                $table->add_field($table->make_radio('payment_method', 'paypal', (isset($data['payment_method']) && $data['payment_method'] == 'paypal' ? true : false), '', 'Pay With PayPal') . $table->make_radio('payment_method', 'cc', (isset($data['payment_method']) && $data['payment_method'] == 'cc' ? true : false), '', '<span title="Add a Credit-Card First">Pay With Credit-Card</span>'), 'l');
                $table->add_field('Automatically Charge CC');
                $table->add_field(make_select('cc_auto', ['0', '1'], ['No', 'Yes'], ($data['cc_auto'] ?? '1')));
                $table->add_field($table->make_submit('Update', false, false, 'style="color: white; padding: 0.1em 0.5em; background: none repeat-x scroll 50% 50% #004ccc; border-radius: 10px;" class="ui-button ui-widget ui-state-hover ui-corner-all"'));
            } else {
                $table->set_colspan(4);
                $table->add_field($table->make_radio('payment_method', 'paypal', (isset($data['payment_method']) && $data['payment_method'] == 'paypal' ? true : false), '', 'Pay With PayPal') . $table->make_radio('payment_method', 'cc', false, 'disabled="disabled" title="Add a Credit-Card First"', '<span title="Add a Credit-Card First">Pay With Credit-Card</span>'), 'l');
                $table->add_field($table->make_submit('Update', false, false, 'style="color: white; padding: 0.1em 0.5em; background: none repeat-x scroll 50% 50% #004ccc; border-radius: 10px;" class="ui-button ui-widget ui-state-hover ui-corner-all"'));
            }
            $table->add_row();
            add_output($table->get_table().'</div><br><a href="'.\MyAdmin\App::link('index.php', 'choice=none.manage_cc&action=add&orig_url='.htmlspecial($orig_url)).'" style="color: white; padding: 0.1em 0.5em; background: none repeat-x scroll 50% 50% #004ccc; border-radius: 10px;" class="ui-button ui-widget ui-state-hover ui-corner-all">+ New Billing Source</a><br>');
            break;
    }
    if (isset(\MyAdmin\App::variables()->request['returnURL'])) {
        \MyAdmin\App::session()->appsession('returnURL', \MyAdmin\App::variables()->request['returnURL']);
    }
    $returnURL = \MyAdmin\App::session()->appsession('returnURL');
    if ($returnURL !== null) {
        add_output('<a href="'.\MyAdmin\App::link('index.php', base64_decode($returnURL)).'" style="color: white; padding: 0.1em 0.5em; background: none repeat-x scroll 50% 50% #004ccc; border-radius: 10px;" class="ui-button ui-widget ui-state-hover ui-corner-all" >Return To Order</a>');
    } else {
        add_output('<a href="'.\MyAdmin\App::link('index.php', 'choice=none.view_balance').'" style="color: white; padding: 0.1em 0.5em; background: none repeat-x scroll 50% 50% #004ccc; border-radius: 10px;" class="ui-button ui-widget ui-state-hover ui-corner-all" >View Balance / Make Payment</a>');
    }
}
