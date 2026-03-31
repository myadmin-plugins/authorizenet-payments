---
name: authorizenet-gateway
description: Creates or modifies Authorize.Net gateway API calls following the project's curl+CSV response pattern. Handles Auth_Data setup, getcurlpage() posting to transact.dll, response field mapping, and cc_log insertion. Use when user says 'add gateway method', 'new transaction type', 'modify refund', or edits src/AuthorizeNetCC.php. Do NOT use for admin UI pages or card validation logic.
---
# Authorize.Net Gateway API Calls

## Critical

- **Never expose credentials in logs or cc_log.** Always `unset($rargs['x_Login'], $rargs['x_Password'], $rargs['x_Delim_Data'], $rargs['x_Encap_Char'])` before writing request fields to `cc_log`.
- **Always use `getcurlpage()`** to POST to `https://secure.authorize.net/gateway/transact.dll`. Never use raw curl or Guzzle.
- **Always parse responses with `str_getcsv()`** — the gateway returns CSV with `"` encapsulation, not JSON.
- **Always log via `make_insert_query('cc_log', $cc_log)`** — never build INSERT strings manually.
- **Response code `$tresponse[0]`** determines success: `1` = Approved, `2` = Declined, `3` = Error, `4` = Held for review.
- **Transaction types** recognized by Authorize.Net: `AUTH_CAPTURE`, `AUTH_ONLY`, `CAPTURE_ONLY`, `CREDIT`, `PRIOR_AUTH_CAPTURE`, `VOID`.

## Instructions

### Step 1: Set up Auth_Data or $args array

Two patterns exist in this codebase. Choose the right one:

**Pattern A — AuthorizeNetCC class method** (`src/AuthorizeNetCC.php`): Use the class's `$this->Auth_Data` property. Set transaction-specific fields on it:

```php
$this->Auth_Data['x_type'] = 'CREDIT';  // or 'Void', etc.
$this->Auth_Data['x_trans_id'] = $trans_id;
$this->Auth_Data['x_card_num'] = $cc_num;
$this->Auth_Data['x_amount'] = $amount;
$this->Auth_Data['x_description'] = 'Description of action';
$this->Auth_Data['x_duplicate_window'] = '0';  // optional, prevents dup filtering
```

The class already initializes these base fields — do NOT re-set them:
```php
private $Auth_Data = [
    'x_Login' => AUTHORIZENET_LOGIN,
    'x_Password' => AUTHORIZENET_PASSWORD,
    'x_Version' => '3.1',
    'x_Delim_Data' => 'TRUE',
    'x_Encap_Char' => '"'
];
```

**Pattern B — Standalone function** (`src/cc.inc.php`): Build a full `$args` array inline:

```php
$args = [
    'x_Login' => AUTHORIZENET_LOGIN,
    'x_Password' => AUTHORIZENET_PASSWORD,
    'x_Type' => 'AUTH_CAPTURE',  // or 'AUTH_ONLY'
    'x_Version' => '3.1',
    'x_Delim_Data' => 'TRUE',
    'x_Encap_Char' => '"',
    'x_Description' => $charge_desc,
    'x_Amount' => $amount,
    'x_Cust_ID' => $custid,
    'x_Email' => $data['account_lid'] ?? '',
    'x_First_Name' => $first_name ?? '',
    'x_Last_Name' => $last_name ?? '',
    'x_Company' => isset($data['company']) ? str_replace(',', ' ', $data['company']) : '',
    'x_Address' => isset($data['address']) ? str_replace(',', ' ', $data['address']) : '',
    'x_City' => isset($data['city']) ? str_replace(',', ' ', $data['city']) : '',
    'x_State' => isset($data['state']) ? str_replace(',', ' ', $data['state']) : '',
    'x_Zip' => $data['zip'] ?? '',
    'x_Country' => $data['country'] ?? '',
    'x_Phone' => isset($data['phone']) ? str_replace(',', ' ', $data['phone']) : '',
    'x_Card_Num' => $cc,
    'x_Exp_Date' => $cc_exp
];
```

Note: Class methods use `x_type` (lowercase t), standalone functions use `x_Type` (capital T). Follow the existing convention for whichever pattern you're extending.

Verify: The args array includes all 5 base auth fields (`x_Login`, `x_Password`, `x_Version`, `x_Delim_Data`, `x_Encap_Char`) before proceeding.

### Step 2: Validate inputs before making the API call

Every method in `AuthorizeNetCC` validates required params and returns an error string on failure:

```php
if (!$cc_num) {
    return 'Error! Credit card Number is empty';
}
if (!$trans_id) {
    return 'Error! Transaction id is empty';
}
if (!$amount) {
    return 'Error! Amount is empty';
}
```

Standalone functions check differently — they return `$retval = false` and may call `add_output()` for UI messages.

Verify: Every required parameter has a guard clause before the `getcurlpage()` call.

### Step 3: Log before the API call

Always log the operation being attempted using `myadmin_log()`:

```php
myadmin_log('billing', 'info', "CC Refund - Initializing with cc num {$cc_num} Transaction id {$trans_id} and Amount {$amount}", __LINE__, __FILE__);
```

Use module `'billing'`, level `'info'` or `'debug'`. Include masked/partial CC info — never log full card numbers in plain text in non-debug contexts. For debug-level request logging:

```php
myadmin_log('billing', 'debug', 'CC Request: '.json_encode($args, true), __LINE__, __FILE__);
```

Verify: `myadmin_log()` call exists before the `getcurlpage()` call.

### Step 4: POST to the gateway and parse CSV response

```php
$options = [
    CURLOPT_REFERER => 'https://admin.trouble-free.net/',
    CURLOPT_SSL_VERIFYPEER => false,
];
$cc_response = getcurlpage('https://secure.authorize.net/gateway/transact.dll', $this->Auth_Data, $options);
$tresponse = str_getcsv($cc_response);
```

The referer URL varies by context:
- Admin/class operations: `'https://admin.trouble-free.net/'`
- Customer-facing charges: `'https://my.interserver.net/'`

Verify: `$tresponse` is an array parsed from CSV. `$tresponse[0]` holds the response code.

### Step 5: Build the cc_log array

Always start with these three fields:

```php
$cc_log = [
    'cc_id' => null,
    'cc_custid' => $custid,
    'cc_timestamp' => mysql_now()
];
```

Then add request fields (strip credentials first):

```php
$rargs = $this->Auth_Data;  // or $args for standalone
unset($rargs['x_Login'], $rargs['x_Password'], $rargs['x_Delim_Data'], $rargs['x_Encap_Char']);
foreach ($rargs as $field => $value) {
    $cc_log['cc_request_'.mb_substr(strtolower($field), 2)] = $value;
}
```

The `mb_substr(strtolower($field), 2)` strips the `x_` prefix and lowercases: `x_Card_Num` → `card_num`, so the column becomes `cc_request_card_num`.

**Special case for trans_id in void:** If `x_trans_id` is a request parameter (not a result), move it to `cc_result_trans_id`:

```php
if (isset($cc_log['cc_request_trans_id'])) {
    $cc_log['cc_result_trans_id'] = $cc_log['cc_request_trans_id'];
    unset($cc_log['cc_request_trans_id']);
}
```

Verify: `cc_log` has `cc_id`, `cc_custid`, `cc_timestamp`, and no credential fields.

### Step 6: Map response fields into cc_log

Use the standard field mapping array (68 elements, indices 0–67):

```php
$fields = [
    'code', 'subcode', 'reason_code', 'reason_text', 'auth_code', 'avs_code',
    'trans_id', 'invoice_num', 'description', 'amount', 'method', 'trans_type',
    'customer_id', 'first_name', 'last_name', 'company', 'address', 'city',
    'state', 'zip', 'country', 'phone', 'fax', 'email',
    'shipto_last_name', 'shipto_first_name', 'shipto_company', 'shipto_address',
    'shipto_city', 'shipto_state', 'shipto_zip', 'shipto_country',
    'tax', 'duty', 'freight', 'tax_exempt', 'purchase_order_num', 'md5',
    'card_code', 'card_verification',
    '', '', '', '', '', '', '', '', '', '',
    'account_num', 'card_type',
    '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''
];
foreach ($tresponse as $idx => $value) {
    if (isset($fields[$idx]) && $fields[$idx] != '') {
        $response[$fields[$idx]] = $value;
        if ($value != '') {
            $cc_log['cc_result_'.$fields[$idx]] = $value;
        }
    }
}
```

Note: Empty strings in `$fields` are reserved/unused positions — skip them. Non-empty response values get prefixed `cc_result_` in the log.

Verify: `$response` array is populated. `$cc_log` has `cc_result_code`, `cc_result_trans_id`, etc.

### Step 7: Insert cc_log on success

Only insert to `cc_log` when the transaction was approved (`$tresponse[0] == 1`):

```php
if ($tresponse['0'] == 1) {
    $db = clone $GLOBALS['tf']->db;
    $db->query(make_insert_query('cc_log', $cc_log), __LINE__, __FILE__);
}
```

Exception: `auth_charge_card()` inserts cc_log for ALL responses (success or failure) — follow the convention of the function you're extending.

Verify: `make_insert_query()` is used — never raw SQL string building.

### Step 8: Log completion and return

```php
myadmin_log('billing', 'info', 'CC Refund - Completed values returned Response :'.json_encode($cc_log), __LINE__, __FILE__);
return $tresponse;
```

Class methods return `$tresponse` (the raw CSV array). Standalone functions interpret the response code and return `true`/`false`:

```php
switch ($response['code']) {
    case '1':
        $retval = true;
        break;
    default:
        myadmin_log('billing', 'notice', 'FAILURE ('.$custid.' '.mask_cc($cc, true).' '.$amount.')', __LINE__, __FILE__);
        break;
}
return $retval;
```

Verify: Return type matches the pattern being extended (array for class, bool for standalone).

## Examples

### User says: "Add a PRIOR_AUTH_CAPTURE method to AuthorizeNetCC"

Actions taken:
1. Read `src/AuthorizeNetCC.php` to confirm class structure and existing methods.
2. Add a new method following the `refund()` / `voidTransaction()` pattern:

```php
/**
 * captures a previously authorized transaction
 *
 * @param $trans_id (16 digit Transaction Id from AUTH_ONLY)
 * @param $amount (amount to capture, must be <= original auth amount)
 * @param $custid
 * @return array|string
 */
public function capture($trans_id, $amount, $custid)
{
    if (!$trans_id) {
        return 'Error! Transaction id is empty';
    }
    if (!$amount) {
        return 'Error! Amount is empty';
    }
    $this->Auth_Data['x_type'] = 'PRIOR_AUTH_CAPTURE';
    $this->Auth_Data['x_trans_id'] = $trans_id;
    $this->Auth_Data['x_amount'] = $amount;
    $this->Auth_Data['x_description'] = 'Capture Previously Authorized Payment';
    $options = [
        CURLOPT_REFERER => 'https://admin.trouble-free.net/',
        CURLOPT_SSL_VERIFYPEER => false,
    ];
    myadmin_log('billing', 'info', "CC Capture - Initializing with Transaction id {$trans_id} and Amount {$amount}", __LINE__, __FILE__);
    $cc_response = getcurlpage('https://secure.authorize.net/gateway/transact.dll', $this->Auth_Data, $options);
    $tresponse = str_getcsv($cc_response);
    $cc_log = [
        'cc_id' => null,
        'cc_custid' => $custid,
        'cc_timestamp' => mysql_now()
    ];
    $rargs = $this->Auth_Data;
    unset($rargs['x_Login'], $rargs['x_Password'], $rargs['x_Delim_Data'], $rargs['x_Encap_Char']);
    if (!empty($rargs)) {
        foreach ($rargs as $field => $value) {
            $cc_log['cc_request_'.mb_substr(strtolower($field), 2)] = $value;
        }
    }
    if (isset($cc_log['cc_request_trans_id'])) {
        $cc_log['cc_result_trans_id'] = $cc_log['cc_request_trans_id'];
        unset($cc_log['cc_request_trans_id']);
    }
    $fields = ['code', 'subcode', 'reason_code', 'reason_text', 'auth_code', 'avs_code', 'trans_id', 'invoice_num', 'description', 'amount', 'method', 'trans_type', 'customer_id', 'first_name', 'last_name', 'company', 'address', 'city', 'state', 'zip', 'country', 'phone', 'fax', 'email', 'shipto_last_name', 'shipto_first_name', 'shipto_company', 'shipto_address', 'shipto_city', 'shipto_state', 'shipto_zip', 'shipto_country', 'tax', 'duty', 'freight', 'tax_exempt', 'purchase_order_num', 'md5', 'card_code', 'card_verification', '', '', '', '', '', '', '', '', '', '', 'account_num', 'card_type', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''];
    foreach ($tresponse as $idx => $value) {
        if (isset($fields[$idx]) && $fields[$idx] != '') {
            $response[$fields[$idx]] = $value;
            if ($value != '') {
                $cc_log['cc_result_'.$fields[$idx]] = $value;
            }
        }
    }
    if ($tresponse['0'] == 1) {
        $db = clone $GLOBALS['tf']->db;
        $db->query(make_insert_query('cc_log', $cc_log), __LINE__, __FILE__);
    }
    myadmin_log('billing', 'info', 'CC Capture - Completed values returned Response :'.json_encode($cc_log), __LINE__, __FILE__);
    return $tresponse;
}
```

Result: New `capture()` method in `AuthorizeNetCC` class following identical structure to `refund()` and `voidTransaction()`.

## Common Issues

### "Undefined constant AUTHORIZENET_LOGIN"
The constants are registered by `src/Plugin.php` via `getSettings()`. Ensure the plugin is loaded:
- Check `include/config/plugins.json` contains `detain/myadmin-authorizenet-payments`
- Settings define: `AUTHORIZENET_LOGIN`, `AUTHORIZENET_PASSWORD`, `AUTHORIZENET_KEY`, `AUTHORIZENET_REFERRER`
- For tests, define these constants in your test bootstrap or setUp method

### Response comes back empty or malformed
1. Check that `getcurlpage()` is available — it's a global function, loaded via `function_requirements('getcurlpage')` if needed
2. Verify the POST URL is exactly `https://secure.authorize.net/gateway/transact.dll`
3. Check that `x_Delim_Data` is `'TRUE'` (string) and `x_Encap_Char` is `'"'` — without these, the response won't be parseable CSV

### cc_log insert fails with column mismatch
The `cc_log` table columns use the pattern `cc_request_*` and `cc_result_*`. The field name transformation is `mb_substr(strtolower($field), 2)` which strips the first 2 characters (the `x_` prefix). If you add a custom `x_` field like `x_My_Field`, it becomes `cc_request_my_field`. Verify the column exists in the `cc_log` table before inserting.

### Refund returns response code 3 (Error) with "The referenced transaction does not meet the criteria for issuing a credit"
- Transaction must be settled (not same-day) for `CREDIT` type — use `VOID` for same-day reversals
- The codebase handles this in `src/admin/cc_refund.php:44`: `$do = strtotime(date('Y-m-d', strtotime($cc_log['cc_timestamp']))) == strtotime(date('Y-m-d')) ? 'void' : 'refund';`
- For refunds, only the last 4 digits of the card are required (`$cc_num = mb_substr($card, -4)`)

### `$response` variable used before initialization
The `$response` array is not explicitly initialized in the class methods — it's built by the `foreach` over `$tresponse`. If the gateway returns an empty response, `$response` will be undefined. The class methods return `$tresponse` directly, so this only matters in standalone functions where `$response['code']` is checked in a switch statement. Initialize `$response['code'] = 0;` before the API call block (as done in `charge_card()` at line 400).

### Duplicate transaction rejected
Set `$this->Auth_Data['x_duplicate_window'] = '0';` to disable duplicate transaction filtering. The `refund()` method does this; `voidTransaction()` does not (voids are inherently idempotent by trans_id).