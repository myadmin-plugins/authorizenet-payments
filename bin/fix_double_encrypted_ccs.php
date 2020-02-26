#!/usr/bin/env php
<?php
/**
* Fix various account problems
* @author Joe Huss <detain@interserver.net>
* @package MyAdmin
* @category map_everything_to_my
* @copyright 2020
*/
	$_SERVER['HTTP_HOST'] = 'my.interserver.net';

	require_once __DIR__.'/../../include/functions.inc.php';
	//include(INCLUDE_ROOT.'/billing.functions.inc.php');

	$db = $GLOBALS['tf']->db;
	$db2 = clone $db;
	$db->query("select * from accounts_ext where account_key='cc' and length(account_value) > 27");
	while ($db->next_record(MYSQL_ASSOC)) {
		$cc = $db->Record['account_value'];
		while ($cc != $GLOBALS['tf']->decrypt($cc)) {
			$cc = $GLOBALS['tf']->decrypt($cc);
		}
		$cc = str_replace(['-', ' '], ['', ''], $cc);
		$db2->query("update accounts_ext set account_value='".$db2->real_escape($GLOBALS['tf']->encrypt($cc))."' where account_key='cc' and account_id={$db->Record['account_id']}");
	}
