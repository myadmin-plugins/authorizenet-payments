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
$db->query("select * from accounts_ext where account_key='cc' and account_value like '%/./%'");
while ($db->next_record(MYSQL_ASSOC)) {
    $cc = $GLOBALS['tf']->decrypt_old($db->Record['account_value']);
    if ($cc != $db->Record['account_value']) {
        $query = "update accounts_ext set account_value='".$db2->real_escape($cc)."' where account_key='cc' and account_id={$db->Record['account_id']}";
        echo "$query\n";
        $db2->query($query);
    }
}
$db->query("select * from accounts_ext where account_key='ccs' and account_value like '%/./%'");
while ($db->next_record(MYSQL_ASSOC)) {
    $ccs = myadmin_unstringify($db->Record['account_value']);
    if (is_array($ccs)) {
        $changed = false;
        foreach ($ccs as $cc_idx => $data) {
            if (isset($data['cc'])) {
                $ccs[$cc_idx]['cc'] = $GLOBALS['tf']->decrypt_old($data['cc']);
                $changed = true;
            }
        }
        if ($changed == true) {
            $ccs = myadmin_stringify($ccs);
            $query = "update accounts_ext set account_value='".$db2->real_escape($ccs)."' where account_key='ccs' and account_id={$db->Record['account_id']}";
            echo "$query\n";
            $db2->query($query);
        }
    } else {
        //echo "Failed Decoding {$db->Record['account_value']}\n";
    }
}
