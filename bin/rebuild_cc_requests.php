#!/usr/bin/env php
<?php
	include_once __DIR__.'/../../include/functions.inc.php';
	function_requirements('get_authorizenet_fields');
	function_requirements('map_authorizenet_fields');
	$authnet_fields = get_authorizenet_fields();
/**
 * @param $string
 * @return mixed
 */
function charset_decode_utf_8($string)
{
	/* Only do the slow convert if there are 8-bit characters */
	/* avoid using 0xA0 (\240) in ereg ranges. RH73 does not like that */
	if (!preg_match("/[\200-\237]/", $string)
	 && !preg_match("/[\241-\377]/", $string)
	) {
		return $string;
	}

	// decode three byte unicode characters
	$string = preg_replace(
		"/([\340-\357])([\200-\277])([\200-\277])/e",
		"'&#'.((ord('\\1')-224)*4096 + (ord('\\2')-128)*64 + (ord('\\3')-128)).';'",
		$string
	);

	// decode two byte unicode characters
	$string = preg_replace(
		"/([\300-\337])([\200-\277])/e",
		"'&#'.((ord('\\1')-192)*64+(ord('\\2')-128)).';'",
		$string
	);

	return $string;
}

	setlocale(LC_ALL, 'en_GB');

	$lines = explode("\n", file_get_contents(__DIR__.'/cc_charge_requests_raw.json'));
	$fields = ['x_Login', 'x_Password'];
	$keep_fields = ['date', 'lid'];
	$bad_lines = ['{"date":"1;37m)', '{"date":"x_'];
	$results = [];
	foreach ($lines as $idx => $line) {
		ini_set('mbstring.substitute_character', 'none');
		$line = mb_convert_encoding($line, 'UTF-8', 'UTF-8');
		$line = iconv('UTF-8', 'ASCII//TRANSLIT', $line);
		$bad = false;
		foreach ($bad_lines as $bad_line) {
			//myadmin_log('scripts', 'info', "Line {$bad_line}";
			if (mb_substr($line, 0, mb_strlen($bad_line)) == $bad_line) {
				$bad = true;
			}
		}
		if ($bad == true) {
			continue;
		}
		$request = json_decode($line, true);
		if (!is_array($request)) {
			switch (json_last_error()) {
				case JSON_ERROR_NONE:
					echo ' - No errors';
					break;
				case JSON_ERROR_DEPTH:
					echo ' - Maximum stack depth exceeded';
					break;
				case JSON_ERROR_STATE_MISMATCH:
					echo ' - Underflow or the modes mismatch';
					break;
				case JSON_ERROR_CTRL_CHAR:
					echo ' - Unexpected control character found';
					break;
				case JSON_ERROR_SYNTAX:
					echo ' - Syntax error, malformed JSON';
					break;
				case JSON_ERROR_UTF8:
					echo ' - Malformed UTF-8 characters, possibly incorrectly encoded';
					break;
				default:
					echo ' - Unknown error';
					break;
			}
			echo PHP_EOL;
			echo "Line #{$idx} unable to handle: {$line}\n";
		}
		$keys = array_keys($request);
		foreach ($keys as $key) {
			if (preg_match('/^x_(.*)$/', strtolower($key), $matches)) {
				if (!in_array($key, $fields)) {
					$request['cc_request_'.$matches[1]] = $request[$key];
				}
				unset($request[$key]);
			}
		}
		if (null !== $request) {
			$results[] = $request;
		} else {
			echo "Skipping Null  Parsed Line {$line}\n";
		}
		//if (++$a > 1000) exit;
	}
	file_put_contents(__DIR__.'/cc_charge_requests.json', json_encode($results, JSON_PRETTY_PRINT));
