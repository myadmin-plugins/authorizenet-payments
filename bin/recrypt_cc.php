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
$db->query("select * from accounts_ext where account_key='cc' and account_value like '%/./%' and length(account_value) <= 30");
while ($db->next_record(MYSQL_ASSOC)) {
    $cc = $GLOBALS['tf']->decrypt($db->Record['account_value']);
    if ($cc !== false && $cc != $db->Record['account_value']) {
        $query = "update accounts_ext set account_value='".$db2->real_escape($GLOBALS['tf']->encrypt($cc))."' where account_key='cc' and account_id={$db->Record['account_id']}";
        echo "$query\n";
        $db2->query($query);
    }
}
