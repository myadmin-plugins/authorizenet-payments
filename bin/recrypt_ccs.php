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
//$db->query("select * from accounts_ext where account_key='ccs' and (account_value like '%/./%' or account_value like '%/.\\\\/%')");
$db->query("select * from accounts_ext where account_key='ccs' and account_value != ''");
while ($db->next_record(MYSQL_ASSOC)) {
    $ccs = myadmin_unstringify($db->Record['account_value']);
    if (is_array($ccs)) {
        $changed = false;
        foreach ($ccs as $cc_idx => $data) {
            if (isset($data['cc'])) {
                $cc = $GLOBALS['tf']->decrypt($data['cc']);
                if ($cc !== false) {
                    $ccs[$cc_idx]['cc'] = $GLOBALS['tf']->encrypt($cc);
                    $changed = true;
                }
            }
        }
        if ($changed == true) {
            $ccs = myadmin_stringify($ccs);
            $query = "update accounts_ext set account_value='".$db2->real_escape($ccs)."' where account_key='ccs' and account_id={$db->Record['account_id']}";
            echo "$query\n";
            $db2->query($query);
        }
    } else {
        echo "Failed Decoding {$db->Record['account_value']}\n";
    }
}
