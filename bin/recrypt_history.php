#!/usr/bin/env php
<?php
/**
* Fix various account problems
* @author Joe Huss <detain@interserver.net>
* @package MyAdmin
* @category map_everything_to_my
* @copyright 2025
*/
use MyAdmin\Orm\Accounts_Ext;

require_once __DIR__.'/../../include/functions.inc.php';

$db = get_module_db('default');
$db2 = clone $db;
$account = new Accounts_Ext($db2);
$db->query("select * from user_log where history_type='changecc' and (history_new_value like '/./%' or history_old_value like '/./%')");
while ($db->next_record(MYSQL_ASSOC)) {
    $updates = [];
    if (substr($db->Record['history_old_value'], 0, 3) == '/./') {
        $cc = $GLOBALS['tf']->decrypt_old($db->Record['history_old_value']);
        $cc = $GLOBALS['tf']->encrypt($cc);
        $updates[] = "history_old_value='".$db->real_escape($cc)."'";
    }
    if (substr($db->Record['history_new_value'], 0, 3) == '/./') {
        $cc = $GLOBALS['tf']->decrypt_old($db->Record['history_new_value']);
        $cc = $GLOBALS['tf']->encrypt($cc);
        $updates[] = "history_new_value='".$db->real_escape($cc)."'";
    }
    if (sizeof($updates) > 0) {
        $query = "update history_log set ".implode(', ', $updates)." where history_id={$db->Record['history_id']}";
        echo "$query\n";
        $db2->query($query);
    }
}
