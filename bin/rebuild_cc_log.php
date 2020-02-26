#!/usr/bin/env php
<?php
	include_once __DIR__.'/../../include/functions.inc.php';
	$create_query_log = false;
	$instant_queries = false;
	$create_json = true;
	$gb = 1073741824;
	ini_set('memory_limit', 4*$gb);
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
	$charges = [];
	if ($create_query_log == true) {
		$queries = 0;
		$tqueries = 0;
		$fd = fopen('cc_charge_queries.sql', 'wb');
		$last_idx = count($requests) - 1;
		$last_prefix = '';
	}
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
				if ($instant_queries == true) {
					if (!in_array($combined['cc_custid'].$combined['cc_timestamp'].$combined['cc_request_amount'], $taken)) {
						$db->query(make_insert_query('cc_log', $combined), __LINE__, __FILE__);
					}
				}
				if ($create_json == true) {
					$charges[] = $combined;
				}
				if ($create_query_log == true) {
					$orig_query = make_insert_query('cc_log', $combined);
					preg_match("/^(?P<prefix>insert into cc_log \(.*\) values )\(.*\)/iU", $orig_query, $matches);
					$prefix = $matches['prefix'];
					if ($prefix != $last_prefix || $queries % 1000 == 0) {
						if ($prefix != $last_prefix) {
							$queries = 0;
						}
						$last_prefix = $prefix;
						if ($idx > 0) {
							fwrite($fd, ";\n");
						}
						fwrite($fd, "{$prefix}");
					} else {
						fwrite($fd, ',');
					}
					$query = str_replace($prefix, "\n  ", $orig_query);
					fwrite($fd, $query);
					$queries++;
					$tqueries++;
				}
				$found = $ridx;
				break;
			}
		}
		if ($found !== false) {
			unset($results[$found]);
		}
	}
	if ($create_query_log == true) {
		fwrite($fd, ";\n");
		fclose($fd);
		echo "Wrote {$tqueries} Total Queries\n";
	}
	if ($create_json == true) {
		file_put_contents(__DIR__.'/cc_charges.json', json_encode($charges, JSON_PRETTY_PRINT));
	}
