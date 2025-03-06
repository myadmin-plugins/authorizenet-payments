<?php
/**
* Billing Related Services
* @author Joe Huss <detain@interserver.net>
* @copyright 2025
* @package MyAdmin
* @category Billing
*/

use Punic\Currency;
use Brick\Money\Money;
use Brick\Math\RoundingMode;

/**
* @param $cc
* @param bool $last
* @return string
*/
function mask_cc($cc, $last = true)
{
    if (mb_strlen($cc) > 6) {
        $len = mb_strlen($cc) - 4;
        $new = '';
        if ($last === true) {
            $out = '';
            for ($x = 0; $x < $len; $x++) {
                $out .= '*';
            }
            $out .= mb_substr($cc, $len);
        } else {
            $out = mb_substr($cc, 0, 4);
            for ($x = 0; $x < $len; $x++) {
                $out .= '*';
            }
        }
        return $out;
    }
    return $cc;
    //return $out;
}


/**
* @param $cc
* @return bool
*/
function valid_cc($cc)
{
    $schemes = [
        'AMEX' => [
            '/^3[47]\d{13,14}$/'
        ],
        'CHINA_UNIONPAY' => [
            '/^62[0-9]{14,17}$/'
        ],
        'DINERS' => [
            '/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/'
        ],
        'DISCOVER' => [
            '/^6011[0-9]{12}$/',
            '/^64[4-9][0-9]{13}$/',
            '/^65[0-9]{14}$/',
            '/^622(12[6-9]|1[3-9][0-9]|[2-8][0-9][0-9]|91[0-9]|92[0-5])[0-9]{10}$/'
        ],
        'INSTAPAYMENT' => [
            '/^63[7-9][0-9]{13}$/'
        ],
        'JCB' => [
            '/^(?:2131|1800|35[0-9]{3})[0-9]{11}$/'
        ],
        'LASER' => [
            '/^(6304|670[69]|6771)[0-9]{12,15}$/'
        ],
        'MAESTRO' => [
            '/^(6759[0-9]{2})[0-9]{6,13}$/',
            '/^(50[0-9]{4})[0-9]{6,13}$/',
            '/^5[6-9][0-9]{10,17}$/',
            '/^6[0-9]{11,18}$/'
        ],
        'MASTERCARD' => [
            '/^5[1-5][0-9]{14}$/',
            '/^2(22[1-9][0-9]{12}|2[3-9][0-9]{13}|[3-6][0-9]{14}|7[0-1][0-9]{13}|720[0-9]{12})$/'
        ],
        'VISA' => [
            '/^4([0-9]{12}|[0-9]{15})$/'
        ]
    ];
    foreach ($schemes as $cc_type => $cc_regexes) {
        foreach ($cc_regexes as $cc_regex) {
            if (preg_match($cc_regex, $cc)) {
                return true;
            }
        }
    }
    return false;
}

/**
* @return array
*/
function get_locked_ccs()
{
    $ccs = [];
    $accs = [];
    $db = $GLOBALS['tf']->db;
    $db->query(
        "select account_value from accounts, accounts_ext where account_status='locked' and accounts.account_id=accounts_ext.account_id and account_key='cc' group by account_value",
        __LINE__,
        __FILE__
    );
    while ($db->next_record(MYSQL_ASSOC)) {
        if (mb_strlen($db->Record['account_value']) > 10) {
            $ccs[] = $db->Record['account_value'];
        }
    }
    return $ccs;
}

/**
* creates a select box for creditcard expiration dates
*
* @param string default the current expiration date
* @return string returns a select box of possible expiration dates
*/
function select_cc_exp($default)
{
    $months = get_months();
    $default_month = (int) mb_substr($default, 0, 2);
    $default_year = mb_substr($default, 3);
    if (mb_strlen($default_year) == 2) {
        $default_year = '20'.$default_year;
    }
    $default_year = (int) $default_year;
    $minyear = date('Y');
    $maxyear = date('Y') + 10;
    $out = '<select name=exp_month>';
    $size = count($months);
    for ($x = 1; $x < $size; $x++) {
        if ($default_month == $x) {
            $out .= '<OPTION selected value='.$x.'>'.$months[$x].'</OPTION>';
        } else {
            $out .= '<OPTION value='.$x.'>'.$months[$x].'</OPTION>';
        }
    }
    $out .= '</select>';
    $out .= '<select name=exp_year>';
    for ($x = $minyear; $x < $maxyear; $x++) {
        if ($default_year == $x) {
            $out .= '<option selected value='.$x.'>'.$x.'</OPTION>';
        } else {
            $out .= '<option value='.$x.'>'.$x.'</OPTION>';
        }
    }
    $out .= '</select>';
    return $out;
}

/**
* Checks whether or not the customer is allowed to use creditcards.  The current logic for this allows
* CC use if they are white-listed for cc use, or if they have there creditcard authorized, or if both
* there maxmind score and riskscore are under their respective cut-off limits.  It will fail if the
* RiskScore is not set but allows the Score to not be set (since its being phased out)
*
* @param array $data the clients data
* @param array|bool|false $cc_holder optional array that has the 'cc' field in it containing the cc if you dont want ot use data (like from request or the $cc  (parsed $ccs) )
* @param bool $check_disabled_cc
* @param string $cc_field optional field to specify the field in the cc holder that contains the cc number.
* @param bool $set_global_reason defaults to false, set to true to set a global cc_reason field w/ the reason why it was denied.
* @return bool true if they can use cc's, false otherwise.
*/
function can_use_cc($data, $ccData = false, $check_disabled_cc = true, $cc_field = 'cc', $set_global_reason = false)
{
    if ($ccData == false) {
        $ccData = $data;
    }
    // Alternate logic for the same thing, this just figures it out in reverse and in a single if statement.
    // The new code adds a loggable reason for denying the use.
    /*
    $cc_usable = false;
    if (
    (
    (isset($data['cc_whitelist']) && $data['cc_whitelist'] == 1)
    || (isset($cc_holder[$cc_field]) && isset($data['cc_auth_'.$GLOBALS['tf']->decrypt($cc_holder[$cc_field])]))
    || (
    (!isset($data['maxmind_score']) || $data['maxmind_score'] <= MAXMIND_SCORE_DISABLE_CC)
    && (isset($data['maxmind_riskscore']) && $data['maxmind_riskscore'] <= MAXMIND_RISKSCORE_DISABLE_CC)
    )
    )
    && (isset($cc_holder[$cc_field]) && $GLOBALS['tf']->decrypt($cc_holder[$cc_field]) != '')
    && ($check_disabled_cc === false || !isset($data['disable_cc']) || $data['disable_cc'] != 1)
    ) {
    $cc_usable = true;
    }
    */
    $cc_usable = true;
    $reason = '';
    if (!isset($data['cc_whitelist']) || $data['cc_whitelist'] != 1) {
        if (!isset($ccData[$cc_field]) || !isset($data['cc_auth_'.$GLOBALS['tf']->decrypt($ccData[$cc_field])])) {
            if (!isset($ccData[$cc_field]) || trim($GLOBALS['tf']->decrypt($ccData[$cc_field])) == '') {
                $reason .= '  No Credit-Card Number set or the number is blank.';
                $cc_usable = false;
            }
            /*if (isset($cc_holder[$cc_field]) && (!isset($data['cc_auth_'.$GLOBALS['tf']->decrypt($cc_holder[$cc_field])]) || trim($data['cc_auth_'.$GLOBALS['tf']->decrypt($cc_holder[$cc_field])]) == '')) {
                $reason .= " ".$GLOBALS['tf']->decrypt($cc_holder[$cc_field])." is not verified.";
                $cc_usable = false;
            }*/
            if (!isset($data['maxmind_riskscore']) && !isset($ccData['maxmind_riskscore'])) {
                $reason .= '  MaxMind Fraud Risk Score is blank';
                $cc_usable = false;
            } elseif ((isset($ccData['maxmind_riskscore']) && $ccData['maxmind_riskscore'] >= MAXMIND_RISKSCORE_DISABLE_CC)|| $data['maxmind_riskscore'] >= MAXMIND_RISKSCORE_DISABLE_CC) {
                $reason .= "  MaxMind Fraud Risk Score is ".($ccData['maxmind_riskscore'] ?? $data['maxmind_riskscore'])."% chance of Fraud.";
                $cc_usable = false;
            }
            if ($check_disabled_cc == true && isset($data['disable_cc']) && $data['disable_cc'] == 1) {
                $reason .= '  Credit-Cards are disabled.';
                $cc_usable = false;
            }
        }
    }
    if ($set_global_reason === true) {
        $GLOBALS['cc_reason'] = trim($reason);
    }
    if ($cc_usable == false) {
        $reason = trim($reason);
        //myadmin_log('billing', 'debug', "can_use_cc() returned false because: {$reason}", __LINE__, __FILE__);
    }
    return $cc_usable;
}

/**
* formats the form posted cc expiration month and year into the format used in our accounts table
*
* @return string the properly formatted expiration date
*/
function format_cc_exp()
{
    $exp_month = ($GLOBALS['tf']->variables->request['exp_month'] ?? 1);
    $exp_year = ($GLOBALS['tf']->variables->request['exp_year'] ?? date('Y'));
    if (mb_strlen($exp_month) == 1) {
        $exp_month = '0'.$exp_month;
    }
    $value = $exp_month.'/'.$exp_year;
    return $value;
}

/**
* generates a cc decline email body
*
* @param int $custid     the customer id #
* @param int $invoice_id the invoice id #
* @return array array w/ the information needed to send an email or display the cc decline
* @throws \Exception
* @throws \SmartyException
*/
function make_cc_decline($custid, $invoice_id)
{
    $admin_dir = INSTALL_ROOT;
    $data = $GLOBALS['tf']->accounts->read($custid);
    $domain = $GLOBALS['tf']->accounts->cross_reference($custid);
    $groupinfo = get_groupinfo($domain);
    if ($groupinfo['email'] != '') {
        $emailfrom = $groupinfo['email'];
    } else {
        $emailfrom = EMAIL_FROM;
    }
    $smarty = new TFSmarty();
    $smarty->assign('invoice_id', $invoice_id);
    $smarty->assign('customer_domain', $domain);
    $smarty->assign('customer_id', $custid);
    $smarty->assign('customer_name', $data['name']);
    $smarty->assign('company_name', $groupinfo['account_lid']);
    $invoice_data = get_invoice($invoice_id);
    $smarty->assign('customer_balance', $invoice_data['invoices_amount']);
    if (DOMAIN == 'interserver.net' || trim(DOMAIN) == '') {
        $smarty->assign('url', 'my.interserver.net');
    } else {
        $smarty->assign('url', DOMAIN.URLDIR);
    }
    $ret_invoice['invoice'] = $smarty->fetch('email/client/ccdecline.tpl');
    $ret_invoice['toname'] = $data['name'];
    $ret_invoice['toemail'] = get_invoices_email($data);
    $ret_invoice['subject'] = 'Problem With Account '.$domain;
    $ret_invoice['fromname'] = $groupinfo['account_lid'].' Billing Department';
    $ret_invoice['fromemail'] = $emailfrom;
    return $ret_invoice;
}

/**
* sends a cc decline email
*
* @param int $custid
* @param mixed $invoice_id
* @return void
*/
function email_cc_decline($custid, $invoice_id)
{
    $email = make_cc_decline($custid, $invoice_id);
    myadmin_log('billing', 'debug', '    Emailing CC Decline Message To '.$email['toname'], __LINE__, __FILE__);
    (new \MyAdmin\Mail())->multiMail($email['subject'], '<PRE>'.$email['invoice'].'</PRE>', $email['toemail'], 'client/ccdecline.tpl');
}

/**
* given the account data array, it parses out and returns an array of ccs
*
* @param array $data account data array
* @return array the ccs array parsed and decrypted
*/
function parse_ccs($data)
{
    $tf = $GLOBALS['tf'];
    $ccs = (isset($data['ccs']) ? myadmin_unstringify($data['ccs']) : []);
    $repl = [' ', '_', '-'];
    $with = ['', '', ''];
    if (isset($data['cc']) && $data['cc'] != '') {
        $cc = trim(str_replace($repl, $with, trim($tf->decrypt($data['cc']))));
        $found = false;
        if (count($ccs) > 0) {
            foreach ($ccs as $temp_cc) {
                if (trim(str_replace($repl, $with, $tf->decrypt($temp_cc['cc']))) == $cc) {
                    $found = true;
                }
            }
        }
        if ($found == false) {
            $ccs[] = ['cc' => $cc, 'cc_exp' => $data['cc_exp'] ?? ''];
        }
        //$ccs[] = array('cc' => $tf->encrypt($cc), 'cc_exp' => isset($data['cc_exp']) ? $data['cc_exp'] : '');
    }
    return $ccs;
}

/**
* gets a list of bad cc numbers
*
* @return array array of cc #s
*/
function get_bad_cc()
{
    return myadmin_unstringify(trim(file_get_contents(INCLUDE_ROOT.'/config/bad_ccs.json')));
}

/**
* Charges a given customers credit-card for the given amount
*
* @param integer $custid the id of the customer
* @param bool|float $amount the amount to charge
* @param bool|array|int $invoice the invoices to charge, can be a single invoice id in a string, or an array of invoiceids.
* @param string $module the module the invoices use.
* @param bool|string $returnURL defaults to false, dont include a return / try again url, true to use the current url, or a string specifying the url
* @param bool $useHandlePayment defaults to true, whether or not to call the handle payment processing after a successfull charge
* @param bool|string $queue optional whether or not to queue the payment processing / activation code, defaults to false, or can be a string redirect url (assumes queue true)
* @return bool whether or not the charge was successfull.
* @throws \Exception
* @throws \SmartyException
*/
function charge_card($custid, $amount = false, $invoice = false, $module = 'default', $returnURL = false, $useHandlePayment = true, $queue = false)
{
    $custid = (int) $custid;
    if ($invoice) {
        if (!is_array($invoice)) {
            $invoice = [$invoice];
        }
    }
    $module = get_module_name($module);
    $settings = \get_module_settings($module);
    $db = get_module_db($module);
    $retval = false;
    $data = $GLOBALS['tf']->accounts->read($custid);
    if (isset($data['disable_cc']) && $data['disable_cc'] == 1) {
        add_output('<div class="container alert alert-danger"><strong>Error! CC Disabled! </strong>Payment type credit card is currently unavailable. Remove the credit card(s) you have on file and add them again. If you continue having issues please contact us.</div>');
        return $retval;
    }
    if (!isset($data['cc']) && !isset($GLOBALS['tf']->variables->request['ot_cc'])) {
        global $webpage;
        if (isset($webpage) && $webpage == true) {
            add_output('<div class="container alert alert-danger"><strong>Error! No CC On File! </strong>We have no credit-card on file.  Please go to Billing -> Manage Credit Cards and set one or contact support for assistance.</div>');
        }
        return $retval;
    }
    if (!isset($data['cc_exp']) && !isset($GLOBALS['tf']->variables->request['ot_cc'])) {
        global $webpage;
        if (isset($webpage) && $webpage == true) {
            add_output('<div class="container alert alert-danger"><strong>Error! No CC Expiration Date On File! </strong>We have no credit-card exp date on file.  Please go to Billing -> Manage Credit Cards and set one or contact support for assistance.</div>');
        }
        return $retval;
    }
    if ($amount === false) {
        foreach ($invoice as $tinvoice) {
            $invoice_data = get_invoice($tinvoice, $module);
            $amount = bcadd($amount, convertCurrency($invoice_data['invoices_amount'], 'USD', $invoice_data['invoices_currency'])->getAmount()->toFloat(), 2);
        }
    }
    $lid = $GLOBALS['tf']->accounts->cross_reference($custid);
    $amount = round((float) $amount, 2);
    // do some extra sanity checks
    $name = explode(' ', (!isset($data['name']) || trim($data['name']) == '' ? str_replace('@', ' ', $data['account_lid']) : $data['name']));
    $first_name = $name[0];
    $last_name = $name[count($name) - 1];
    $charge_desc = $lid;
    $response['code'] = 0;
    //$cc = $data['cc'];
    $ccs = parse_ccs($data);
    $cc = isset($GLOBALS['tf']->variables->request['ot_cc']) && isset($ccs[$GLOBALS['tf']->variables->request['ot_cc']]) ? $GLOBALS['tf']->decrypt($ccs[$GLOBALS['tf']->variables->request['ot_cc']]['cc']) : $GLOBALS['tf']->decrypt($data['cc']);
    $cc = trim($cc);
    $cc = str_replace([' ', '_', '-'], ['', '', ''], $cc);
    $badcc = get_bad_cc();
    if (in_array($cc, $badcc)) {
        if (isset($webpage) && $webpage == true) {
            add_output('<div class="container alert alert-danger"><strong>Error! Bad CC Number! </strong>This Credit-Card Number has been determined unusable or bad.</div>');
        }
        return $retval;
    }
    $orig_amount = $amount;
    $skip_prepay = 0;
    $prepay_invoices = [];
    foreach ($invoice as $tinvoice) {
        $invoice_data = get_invoice($tinvoice, $module);
        if ($invoice_data !== false && preg_match('/Prepay ID (?P<pid>\d+)\sInvoice/', $invoice_data['invoices_description'], $matches_arr)) {
            $skip_prepay = 1;
            $prepay_invoices[$invoice_data['invoices_id']] = $invoice_data;
            $prepay_invoices[$invoice_data['invoices_id']]['prepay_id'] = $matches_arr['pid'];
        }
    }
    $prepay_amount = 0;
    if (!$skip_prepay) {
        $prepay_amount = get_prepay_related_amount($invoice, $module);
        if ($prepay_amount > 0) {
            if ($amount - $prepay_amount < 0) {
                $prepay_amount = $amount;
            }
            $amount = bcsub($amount, $prepay_amount, 2);
            myadmin_log('billing', 'debug', "Now Amount {$amount}  Prepay {$prepay_amount}", __LINE__, __FILE__);
        }
    }
    $cc_parts = explode('/', (isset($data['cc_exp']) ? trim(str_replace(' ', '', (strpos($data['cc_exp'], '/') !== false ? $data['cc_exp'] : substr($data['cc_exp'], 0, 2).'/'.substr($data['cc_exp'], 2)))) : date('m/Y')));
    if (isset($GLOBALS['tf']->variables->request['ot_cc'])) {
        $cc_parts = explode('/', (isset($ccs[$GLOBALS['tf']->variables->request['ot_cc']]['cc_exp']) ? trim(str_replace(' ', '', (strpos($ccs[$GLOBALS['tf']->variables->request['ot_cc']]['cc_exp'], '/') !== false ? $ccs[$GLOBALS['tf']->variables->request['ot_cc']]['cc_exp'] : substr($ccs[$GLOBALS['tf']->variables->request['ot_cc']]['cc_exp'], 0, 2).'/'.substr($ccs[$GLOBALS['tf']->variables->request['ot_cc']]['cc_exp'], 2)))) : date('m/Y')));
    }
    $cc_exp = $cc_parts[0].'/'.(isset($cc_parts[1]) ? (mb_strlen($cc_parts[1]) == 2 ? '20'.$cc_parts[1] : $cc_parts[1]) : date('Y'));
    myadmin_log('billing', 'notice', "Charging {$lid} ({$data['status']}) ".($amount == $orig_amount ? $amount : $amount.' and the rest of the '.$orig_amount.' paid via prepay (had '.$prepay_amount.' prepays available)').'} Using Creditcard '.mask_cc($cc).' (disabled '.(isset($data['disable_cc']) && $data['disable_cc'] == 1 ? 'yes' : 'no').')', __LINE__, __FILE__);
    if ($amount == 0) {
        // approve if they have no balance
        $response['code'] = 1;
    } elseif (trim($cc) == '') {
        //myadmin_log('billing', 'notice', 'Blank Credit Card', __LINE__, __FILE__);
        add_output('<div class="container alert alert-danger"><strong>Error! Blank Credit Card! </strong>No Credit Card number found.</div>');
        $response['code'] = 0;
        return $retval;
    } else {
        $args = [
            'x_Login' => AUTHORIZENET_LOGIN,
            'x_Password' => AUTHORIZENET_PASSWORD,
            'x_Type' => 'AUTH_CAPTURE',
            'x_Version' => '3.1',
            'x_Delim_Data' => 'TRUE',
            'x_Encap_Char' => '"',
            'x_Description' => 'Hosting Charge ('.$charge_desc.')',
            'x_Amount' => $amount,
            'x_Cust_ID' => $custid,
            'x_Email' => $data['account_lid'] ?? '',
            'x_First_Name' => $first_name ?? '',
            'x_Last_Name' => $last_name ?? '',
            'x_Company' => isset($data['company']) ? str_replace(',', ' ', $data['company']) : '',
            'x_Address' => isset($data['address']) ? str_replace(',', ' ', $data['address']) : '',
            'x_City' => isset($data['city']) ? str_replace(',', ' ', $data['city']) : '',
            'x_State' => isset($data['state']) ? str_replace(',', ' ', $data['state']) : '',
            'x_Zip' => $data['zip'] ?? '',
            'x_Country' => $data['country'] ?? '',
            'x_Phone' => isset($data['phone']) ? str_replace(',', ' ', $data['phone']) : '',
            'x_Card_Num' => $cc ?? '',
            'x_Exp_Date' => $cc_exp
        ];
        if (isset($GLOBALS['tf']->variables->request['cc_ccv2']) && in_array(mb_strlen($GLOBALS['tf']->variables->request['cc_ccv2']), [3, 4])) {
            $args['x_Card_Code'] = $GLOBALS['tf']->variables->request['cc_ccv2'];
        }
        if ($invoice) {
            $args['x_Invoice_Num'] = implode(',', $invoice);
            $args['x_Description'] = 'Payment For Invoice '.implode(',', $invoice);
        }
        $options = [
            CURLOPT_REFERER => 'https://my.interserver.net/',
            CURLOPT_SSL_VERIFYPEER => false, // whether or not to validate the ssl cert of the peer
            // 'CURLOPT_CAINFO' => '/usr/share/curl/curl-ca-bundle.crt', // this option really is only useful if CURLOIPT_SSL_VERIFYPEER is TRUE
        ];
        //myadmin_log('billing', 'debug', 'CC Request: '.str_replace("\n", '', var_export($args, TRUE)), __LINE__, __FILE__);
        $cc_response = getcurlpage('https://secure.authorize.net/gateway/transact.dll', $args, $options);
        //myadmin_log('billing', 'debug', 'CC Response: '.$cc_response, __LINE__, __FILE__);
        $tresponse = str_getcsv($cc_response);
        $cc_log = [
            'cc_id' => null,
            'cc_custid' => $custid,
            'cc_timestamp' => mysql_now()
        ];
        $rargs = $args;
        unset($rargs['x_Login'], $rargs['x_Password'], $rargs['x_Delim_Data'], $rargs['x_Encap_Char']);
        foreach ($rargs as $field => $value) {
            $cc_log['cc_request_'.mb_substr(strtolower($field), 2)] = $value;
        }
        $fields = ['code', 'subcode', 'reason_code', 'reason_text', 'auth_code', 'avs_code', 'trans_id', 'invoice_num', 'description', 'amount', 'method', 'customer_id', 'trans_type', 'first_name', 'last_name', 'company', 'address', 'city', 'state', 'zip', 'country', 'phone', 'fax', 'email', 'shipto_last_name', 'shipto_first_name', 'shipto_company', 'shipto_address', 'shipto_city', 'shipto_state', 'shipto_zip', 'shipto_country', 'tax', 'duty', 'freight', 'tax_exempt', 'purchase_order_num', 'md5', 'card_code', 'card_verification', '', '', '', '', '', '', '', '', '', '', 'account_num', 'card_type', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''];
        foreach ($tresponse as $idx => $value) {
            if (isset($fields[$idx]) && $fields[$idx] != '') {
                $response[$fields[$idx]] = $value;
                if ($value != '') {
                    $cc_log['cc_result_'.$fields[$idx]] = $value;
                }
            }
        }
        $db->query(make_insert_query('cc_log', $cc_log), __LINE__, __FILE__);
        if (isset($response['trans_id'])) {
            global $cc_trans_id;
            $cc_trans_id = $response['trans_id'];
        }
        //request_log($module, $custid, __FUNCTION__, 'authorizenet', 'auth_capture', $rargs, $response);
        unset($rargs);
    }
    switch ($response['code']) {
        case '1':
            $retval = true;
            if ($prepay_amount > 0) {
                use_prepay_related_amount($invoice, $module, $prepay_amount);
                if ($useHandlePayment === true) {
                    handle_payment($custid, $prepay_amount, $invoice, 12, $module, '', 'USD', $queue);
                }
                myadmin_log('billing', 'notice', '    CC Charge Successfully Used Partial Prepay Amount '.$prepay_amount, __LINE__, __FILE__);
                $subject = 'CC Charge Auto Used Partial Prepay';
                $email = "Module {$module}<br>Original Amount: {$orig_amount}<br>Prepay Amount {$prepay_amount}<br>Charged Amount {$amount}<br>Invoices ".implode(',', $invoice).'<br>';
                (new \MyAdmin\Mail())->adminMail($subject, $email, false, 'client/payment_approved.tpl');
            }
            if ($amount > 0 && $useHandlePayment === true) {
                handle_payment($custid, $amount, $invoice, 11, $module, ($response['trans_id'] ?? ''), 'USD', $queue);
            }
            //Prepay Invoices updates
            if (!empty($prepay_invoices)) {
                foreach ($prepay_invoices as $invoice_idd => $invoice_dataa) {
                    $db->query("SELECT * FROM prepays WHERE prepay_id = {$invoice_dataa['prepay_id']}");
                    $db->next_record(MYSQL_ASSOC);
                    $remaining = Money::of($db->Record['prepay_remaining'], $db->Record['prepay_currency'], null, RoundingMode::UP)
                        ->plus(convertCurrency($invoice_dataa['invoices_amount'], $db->Record['prepay_currency'], 'USD'), RoundingMode::UP)
                        ->getAmount()
                        ->toFloat();
                    $GLOBALS['tf']->history->add('prepay_cc', $invoice_dataa['prepay_id'], $remaining, $db->Record['prepay_remaining'], $custid);
                    $db->query("UPDATE prepays SET prepay_remaining='{$remaining}', prepay_status=1 WHERE prepay_id='{$invoice_dataa['prepay_id']}'", __LINE__, __FILE__);
                    myadmin_log('payments', 'info', "Applied {$remaining} {$invoice_dataa['invoices_currency']} To Prepay {$invoice_dataa['prepay_id']}", __LINE__, __FILE__);
                    $db->query(make_insert_query('comment_log', [
                        'history_id' => null,
                        'history_sid' => $GLOBALS['tf']->session->sessionid,
                        'history_timestamp' => mysql_now(),
                        'history_creator' => $GLOBALS['tf']->session->account_id,
                        'history_owner' => $custid,
                        'history_section' => 'prepay',
                        'history_type' => 'comment',
                        'history_new_value' => 'Added '.$remaining.' '.$invoice_dataa['invoices_currency'].' from '.ucwords('CreditCard').' Txn '.$response['trans_id'],
                        'history_old_value' => $invoice_dataa['prepay_id']
                    ]), __LINE__, __FILE__);
                }
                //Prepay Invoices updates ends
            }
            break;
        default:
            myadmin_log('billing', 'notice', 'FAILURE (custid:'.$custid.',exp:'.$cc_exp.',cc:'.mask_cc($cc, true).',amount:'.$amount.', code:'.$response['code'].') raw: '.$cc_response, __LINE__, __FILE__);
            add_output('<div class="container alert alert-danger"><div style="width: 40%;text-align: left;margin-left: 10%;"><strong>Error! Your credit card has declined the transaction. </strong><br><br><p>The most common reasons for declines are:</p><ul><li>Incorrect credit card number or expiration date</li><li>Insufficient funds in your credit card</li><li>The bank declined based on purchase history</li><li>The bank\'s fraud rules blocked the transaction</li></ul><br><p>Please contact your bank for reason and try again.</p></div></div>');
            if ($cc_log['cc_result_reason_text'] == 'Declined  (Card reported lost or stolen - Contact card issuer for resolution.)') {
                (new \MyAdmin\Mail())->adminMail('Stolen Credit Card', print_r($cc_log, true), 'billing@interserver.net', 'admin/cc_bad_response.tpl');
            }
            if (mb_strpos($cc_response, ',') === false) {
                myadmin_log('billing', 'warning', 'Invalid cc response', __LINE__, __FILE__);
                (new \MyAdmin\Mail())->adminMail('Invalid CreditCard Response', print_r($cc_log, true), false, 'admin/cc_bad_response.tpl');
                $response['code'] = 0;
                return $retval;
            }
            //$data['status'] = 'pending-fixcc';
            $GLOBALS['tf']->accounts->update($custid, ['payment_method' => 'paypal']);
            $subject = $settings['TITLE'].' Credit Card Payment Declined';
            $smarty = new TFSmarty();
            $smarty->assign('amount', $amount);
            $smarty->assign('service_name', $settings['TBLNAME']);
            $smarty->assign('company', $settings['TITLE']);
            $smarty->assign('name', $data['name']);
            if (!defined(DOMAIN) || in_array(DOMAIN, ['interserver.net', 'misha.interserver.net', 'mymisha.interserver.net']) || trim(DOMAIN) == '') {
                $smarty->assign('domain', 'my.interserver.net');
            } else {
                $smarty->assign('domain', DOMAIN.URLDIR);
            }
            $rows = [];
            if ($invoice) {
                foreach ($invoice as $invoice_id) {
                    $invoice_id = (int) $invoice_id;
                    $db->query("select * from invoices where invoices_id={$invoice_id}", __LINE__, __FILE__);
                    if ($db->num_rows() > 0) {
                        $db->next_record(MYSQL_ASSOC);
                        $row = [];
                        $row['Invoice ID'] = $db->Record['invoices_id'];
                        $row['Invoice Description'] = $db->Record['invoices_description'];
                        $row['Invoice Date'] = $db->Record['invoices_date'];
                        $row['Invoice Currency'] = $db->Record['invoices_currency'];
                        $row['Invoice Amount'] = $db->Record['invoices_amount'];
                        $serviceInfo = get_service($db->Record['invoices_service'], $module);
                        if ($serviceInfo !== false) {
                            $row[$settings['TBLNAME'].' ID'] = $serviceInfo[$settings['PREFIX'].'_id'];
                            $row[ucwords(str_replace('_', ' ', $settings['TITLE_FIELD']))] = $serviceInfo[$settings['TITLE_FIELD']];
                            if (isset($settings['TITLE_FIELD2']) && $settings['TITLE_FIELD2'] != '') {
                                $row[ucwords(str_replace('_', ' ', $settings['TITLE_FIELD2']))] = $serviceInfo[$settings['TITLE_FIELD2']];
                            }
                            $row[$settings['TBLNAME'].' Type'] = $serviceInfo[$settings['PREFIX'].'_type'];
                        }
                        $rows[] = $row;
                    }
                }
            }
            if ($returnURL !== false) {
                if ($returnURL === true) {
                    if (strpos($_SERVER['REQUEST_URI'], 'view_balance')) {
                        $returnURL = $GLOBALS['tf']->link('cart');
                    } else {
                        $returnURL = $GLOBALS['tf']->link($_SERVER['REQUEST_URI']);
                    }
                }
                $smarty->assign('returnURL', $returnURL);
            }
            $smarty->assign('invoices', $rows);
            $email = $smarty->fetch('email/client/payment_failed.tpl');
            (new \MyAdmin\Mail())->multiMail($subject, $email, get_invoice_email($data), 'client/payment_failed.tpl');
            //email_cc_decline($custid, $invoice);
            //$GLOBALS['tf']->history->add('users', 'carddecline', $data['cc'], $data['cc_exp'], $custid);
            break;
    }
    return $retval;
}

/**
* performs an AUTH_ONLY type charge on a creditcard
*
* @param integer $custid customer id
* @param string $cc credit card number
* @param string $cc_exp cc expiration date in MM/YYYY format
* @param float $amount amount to charge
* @param string $module (optional) module to use
* @param string $charge_desc (optional) description of charge
* @param bool|array $override_data (optional) array of data
* @return bool whether or not the charge was successfull.
*/
function auth_charge_card($custid, $cc, $cc_exp, $amount, $module = 'default', $charge_desc = '', $override_data = false)
{
    $custid = (int) $custid;
    $module = get_module_name($module);
    $settings = \get_module_settings($module);
    $db = get_module_db($module);
    $retval = false;
    $data = $GLOBALS['tf']->accounts->read($custid);
    if ($override_data !== false) {
        foreach ($override_data as $key => $value) {
            $data[$key] = $value;
        }
    }
    $amount = round((float) $amount, 2);
    // do some extra sanity checks
    if (!isset($data['name'])) {
        $data['name'] = '';
    }
    if (mb_strpos($data['name'], ' ') !== false) {
        $name = explode(' ', $data['name']);
    } else {
        $name = [$data['name'], ''];
    }

    $first_name = $name[0];
    $last_name = $name[count($name) - 1];
    $lid = $GLOBALS['tf']->accounts->cross_reference($custid);
    $response['code'] = 0;
    $badcc = get_bad_cc();
    if (in_array($cc, $badcc)) {
        return $retval;
    }
    $orig_amount = $amount;
    myadmin_log('billing', 'notice', "Charging {$lid} ({$data['status']}) {$amount} Using Creditcard Ending In ".mask_cc($cc, true), __LINE__, __FILE__);
    if ($amount == 0) {
        // approve if they have no balance
        $response['code'] = 1;
    } elseif (trim($cc) == '') {
        myadmin_log('billing', 'notice', 'Blank Credit Card', __LINE__, __FILE__);
        $response['code'] = 0;
    } else {
        $args = [
            'x_Login' => AUTHORIZENET_LOGIN,
            'x_Password' => AUTHORIZENET_PASSWORD,
            'x_Type' => 'AUTH_ONLY',
            'x_Version' => '3.1',
            'x_Delim_Data' => 'TRUE',
            'x_Encap_Char' => '"',
            'x_Description' => $data['account_lid'].' '.$charge_desc,
            'x_Amount' => $amount,
            'x_Cust_ID' => $custid,
            //'x_Invoice_Num' => $data['account_lid'],
            //'x_Email' => $data['account_lid'],
            'x_First_Name' => $first_name,
            'x_Last_Name' => $last_name,
            'x_Company' => isset($data['company']) ? str_replace(',', ' ', $data['company']) : '',
            'x_Address' => isset($data['address']) ? str_replace(',', ' ', $data['address']) : '',
            'x_City' => isset($data['city']) ? str_replace(',', ' ', $data['city']) : '',
            'x_State' => isset($data['state']) ? str_replace(',', ' ', $data['state']) : '',
            'x_Zip' => $data['zip'] ?? '',
            'x_Country' => $data['country'] ?? '',
            'x_Phone' => isset($data['phone']) ? str_replace(',', ' ', $data['phone']) : '',
            'x_Card_Num' => $cc,
            'x_Exp_Date' => $cc_exp
        ];
        if (isset($_POST['cc_ccv2']) && in_array(mb_strlen($_POST['cc_ccv2']), [3, 4])) {
            $args['x_Card_Code'] = $_POST['cc_ccv2'];
        }
        $options = [
            CURLOPT_REFERER => 'https://admin.trouble-free.net/',
            CURLOPT_SSL_VERIFYPEER => false, // whether or not to validate the ssl cert of the peer
            // 'CURLOPT_CAINFO' => '/usr/share/curl/curl-ca-bundle.crt', // this option really is only useful if CURLOIPT_SSL_VERIFYPEER is TRUE
        ];
        myadmin_log('billing', 'debug', 'CC Request: '.json_encode($args, true), __LINE__, __FILE__);
        $cc_response = getcurlpage('https://secure.authorize.net/gateway/transact.dll', $args, $options);
        myadmin_log('billing', 'debug', 'CC Response: '.$cc_response, __LINE__, __FILE__);
        $tresponse = str_getcsv($cc_response);
        $cc_log = [
            'cc_id' => null,
            'cc_custid' => $custid,
            'cc_timestamp' => mysql_now()
        ];
        $rargs = $args;
        unset($rargs['x_Login'], $rargs['x_Password'], $rargs['x_Delim_Data'], $rargs['x_Encap_Char']);
        foreach ($rargs as $field => $value) {
            $cc_log['cc_request_'.mb_substr(strtolower($field), 2)] = $value;
        }
        $fields = ['code', 'subcode', 'reason_code', 'reason_text', 'auth_code', 'avs_code', 'trans_id', 'invoice_num', 'description', 'amount', 'method', 'customer_id', 'trans_type', 'first_name', 'last_name', 'company', 'address', 'city', 'state', 'zip', 'country', 'phone', 'fax', 'email', 'shipto_last_name', 'shipto_first_name', 'shipto_company', 'shipto_address', 'shipto_city', 'shipto_state', 'shipto_zip', 'shipto_country', 'tax', 'duty', 'freight', 'tax_exempt', 'purchase_order_num', 'md5', 'card_code', 'card_verification', '', '', '', '', '', '', '', '', '', '', 'account_num', 'card_type', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''];
        foreach ($tresponse as $idx => $value) {
            if (array_key_exists($idx, $fields) && $fields[$idx] != '') {
                $response[$fields[$idx]] = $value;
                if ($value != '') {
                    $cc_log['cc_result_'.$fields[$idx]] = $value;
                }
            }
        }
        $db->query(make_insert_query('cc_log', $cc_log), __LINE__, __FILE__);
        //request_log($module, $custid, __FUNCTION__, 'authorizenet', 'auth_only', $rargs, $response);
        unset($rargs);
    }
    switch ($response['code']) {
        case '1':
            $retval = true;
            return $retval;
            break;
        default:
            myadmin_log('billing', 'notice', 'FAILURE ('.$custid.' '.$cc_exp.' '.mask_cc($cc, true).' '.$amount.')', __LINE__, __FILE__, $module);
            return $retval;
            break;
    }
    return $retval;
}

/**
* gets the cc bank number / bin for the given encrypted cc
*
* @param string $cc the encrypted cc number
* @return string the bank id number(bin)
*/
function get_cc_bank_number($cc)
{
    $cc = $GLOBALS['tf']->decrypt($cc);
    return mb_substr($cc, 0, 6);
}

/**
* gets the cc last 4 digits for the given encrypted cc
*
* @param string $cc the encrypted cc number
* @return string the last 4 digits
*/
function get_cc_last_four($cc)
{
    $cc = $GLOBALS['tf']->decrypt($cc);
    return mb_substr($cc, -4);
}
