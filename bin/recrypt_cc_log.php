#!/usr/bin/env php
<?php
/**
* Fix various account problems
* @author Joe Huss <detain@interserver.net>
* @package MyAdmin
* @category map_everything_to_my
* @copyright 2020
*/
use MyAdmin\Orm\Accounts_Ext;

require_once __DIR__.'/../../include/functions.inc.php';

$db = get_module_db('default');
$db2 = clone $db;
$account = new Accounts_Ext($db2);
$db->query("select * from cc_log where cc_request_card_num like '/./%'");
while ($db->next_record(MYSQL_ASSOC)) {
    $updates = [];
    $cc = $GLOBALS['tf']->decrypt($db->Record['cc_request_card_num']);
    if ($cc !== false) {
        $query = "update cc_log set cc_request_card_num='".$db->real_escape($GLOBALS['tf']->encrypt($GLOBALS['tf']->decrypt($db->Record['cc_request_card_num'])))."' where cc_id={$db->Record['cc_id']}";
        echo "$query\n";
        $db2->query($query);
    }
}
