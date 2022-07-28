#!/usr/bin/env php
<?php
    include_once __DIR__.'/../../include/functions.inc.php';
    function_requirements('get_authorizenet_fields');
    function_requirements('map_authorizenet_fields');
    $authnet_fields = get_authorizenet_fields();
    $log = file_get_contents('cc_charge_results_raw.txt');
    $repls = [];
    $repld = [];
    $lines = explode("\n", file_get_contents(__DIR__.'/cc_charge_requests_raw.json'));
    $fields = ['x_Address', 'x_City', 'x_Company', 'x_Description', 'x_Invoice_Num', 'x_Last_Name', 'x_State'];
    foreach ($lines as $line) {
        $request = json_decode($line, true);
        foreach ($fields as $field) {
            if (isset($request[$field]) && mb_strpos($request[$field], ',') !== false && !in_array($request[$field], $repls)) {
                $repls[] = $request[$field];
                $repld[] = str_replace(',', ' ', $request[$field]);
            }
        }
    }
    //print_r($repld);exit;
    preg_match_all("/.*\[(?P<date>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\].*(?P<data>[0-9],.*) \(.*:(?P<lid>.*)\)$/miU", $log, $matches);
    $results = [];
    foreach ($matches['date'] as $idx => $date) {
        $result = [
            'date' => $date,
            'lid' => $matches['lid'][$idx],
            'data' => str_replace($repls, $repld, html_entity_decode($matches['data'][$idx]))
        ];
        $result = map_authorizenet_fields($result, $authnet_fields);
        $results[] = $result;
        //if (sizeof($results) > 10) break;
    }
    file_put_contents(__DIR__.'/cc_charge_results.json', json_encode($results, JSON_PRETTY_PRINT));
