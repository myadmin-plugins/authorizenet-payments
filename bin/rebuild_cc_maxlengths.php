#!/usr/bin/env php
<?php
	$gb = 1073741824;
	ini_set('memory_limit', 4*$gb);
	include_once __DIR__.'/../../include/functions.inc.php';
	$taken = [];
	$db = clone $GLOBALS['tf']->db;
	$db->query('select concat(cc_custid, cc_timestamp, cc_request_amount) as cc_token from cc_log', __LINE__, __FILE__);
	while ($db->next_record(MYSQL_ASSOC)) {
		$taken[] = $db->Record['cc_token'];
	}
	unset($db);
	$db = $GLOBALS['tf']->db;
	$results = json_decode(file_get_contents(__DIR__.'/cc_charge_results.json'), true);
	echo 'Loaded ' . count($results) . " Results\n";
	$requests = json_decode(file_get_contents(__DIR__.'/cc_charge_requests.json'), true);
	echo 'Loaded ' . count($requests) . " Requests\n";
	$maxlengths = [];
	$values = [];
	$max_size = 50;
	foreach ($requests as $idx => $request) {
		if (!is_array($request) && null === $request) {
			continue;
		}
		$time = $db->fromTimestamp($request['date']);
		$lid = $request['lid'];
		$found = false;
		foreach ($results as $ridx => $result) {
			if (!is_array($result) && null === $result) {
				continue;
			}
			$rtime = $db->fromTimestamp($result['date']);
			$rlid = $result['lid'];
			if ($lid == $rlid && abs($rtime - $time) < 60) {
				$combined = array_merge($request, $result);
				$combined['cc_timestamp'] = $combined['date'];
				$combined['cc_custid'] = intval($GLOBALS['tf']->accounts->cross_reference($combined['lid']));
				unset($combined['date'], $combined['lid']);
				foreach ($combined as $field => $value) {
					$length = mb_strlen($value);
					if (!isset($maxlengths[$field]) || $maxlengths[$field] < $length) {
						$maxlengths[$field] = $length;
					}
					if (!isset($values[$field])) {
						$values[$field] = [];
					}
					if (count($values[$field]) < $max_size && !in_array($value, $values[$field])) {
						$values[$field][] = $value;
					}
				}
				$found = $ridx;
				break;
			}
		}
		if ($found !== false) {
			unset($results[$found]);
		}
	}
	foreach ($maxlengths as $field => $maxlength) {
		$size = count($values[$field]);
		if ($size < $max_size) {
			echo "Possible {$field} Size {$size} ENUM('".implode("','", $values[$field])."')\n";
		} elseif ($maxlength < 255) {
			echo "ALTER TABLE cc_log CHANGE `{$field}` `{$field}` VARCHAR({$maxlength}) NOT NULL;\n";
		} elseif ($maxlength > 255) {
			echo "ALTER TABLE cc_log CHANGE `{$field}` `{$field}` TEXT NOT NULL;\n";
		}
	}
