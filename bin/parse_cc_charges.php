#!/usr/bin/env php
<?php
/**
* Parse logs for cc charges and create cc entries for them
*
* @author Joe Huss <detain@interserver.net>
* @package MyAdmin
* @category Scripts
* @copyright 2025
*/

    require_once __DIR__.'/../../include/functions.inc.php';
    $webpage = false;
    define('VERBOSE_MODE', false);
    $db = $GLOBALS['tf']->db;
    $db2 = get_module_db('vps');
    function_requirements('charge_card');
    function_requirements('auth_charge_card');
/*
| request_id | request_module | request_timestamp   | request_custid | request_function | request_category | request_action | request_request                                                                                                                                                                                                                                                                                                                                                                                                                                          |
request_result                                                                                                                                               |
+------------+----------------+---------------------+----------------+------------------+------------------+----------------+----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+--------------------------------------------------------------------------------------------------------------------------------------------------------------+
|         16 | domains        | 2015-10-06 18:02:32 |         268083 | auth_charge_card | authorizenet     | auth_only      | {"x_Type":"AUTH_ONLY","x_Version":"3.1","x_Delim_Data":"TRUE","x_Description":"militz4beatr1z@gmail.com Validation Random Charge","x_Amount":0.94,"x_Cust_ID":268083,"x_First_Name":"militza","x_Last_Name":"robles","x_Company":"","x_Address":"urb. independecia. 1era etapa,","x_City":"coro","x_State":"Falcon","x_Zip":"4101","x_Country":"Venezuela","x_Phone":"","x_Card_Num":"5415915059494192","x_Exp_Date":"10\/18","x_Card_Code":"098"}       |
{"code":"2","subcode":"1","reason_code":"2","reason_text":"This transaction has been declined.","auth_code":"","avs_code":"X","trans_id":"7591501618"}       |
*/    $db->query("select * from request_log where request_category='authorizenet'");
    $fields = [];
    while ($db->next_record(MYSQL_ASSOC)) {
        $request = json_decode($db->Record['request_request']);
        $result = json_decode($db->Record['request_result']);
        unset($db->Record['request_request'], $db->Record['request_result'], $db->Record['request_category'], $db->Record['request_function'], $db->Record['request_action']);
        if (count($fields) == 0) {
            foreach (array_keys($db->Record) as $key) {
                $fields[] = str_replace('request_', 'cc_', $key);
            }
        }
        foreach ($request as $key => $value) {
            $key = preg_replace('/^x_/', 'cc_request_', strtolower($key));
            if (!in_array($key, $fields)) {
                $fields[] = $key;
            }
        }
        foreach ($result as $key => $value) {
            $key = 'cc_result_'.strtolower($key);
            if (!in_array($key, $fields)) {
                $fields[] = $key;
            }
        }
    }
    $table = "create table `cc_log` (\n";
    foreach ($fields as $field) {
        if ($field == 'cc_id') {
            $table .= "    `{$field}` int(11) unsigned not null auto_increment,\n";
        } elseif ($field == 'cc_timestamp') {
            $table .= "    `{$field}` timestamp not null default CURRENT_TIMESTAMP,\n";
        } else {
            $table .= "    `{$field}` varchar(255) not null default '',\n";
        }
    }
    $table .= "    primary key (`cc_id`),\n";
    $table .= "    key `cc_request_card_num` (`cc_request_card_num`),\n";
    $table .= "    key `cc_custid` (`cc_custid`),\n";
    $table .= ") ENGINE=InnoDB;\n";
    echo $table;
