---
name: cc-function
description: Creates credit card utility functions following patterns in src/cc.inc.php. Handles card validation, masking, encryption via $GLOBALS['tf']->encrypt(), and DB queries with get_module_db(). Use when user says 'add CC function', 'new card utility', or modifies src/cc.inc.php. Do NOT use for gateway API calls (charge_card, auth_charge_card), admin pages (src/admin/), or AuthorizeNetCC class methods.
---
# CC Function

## Critical

- **All functions go in `src/cc.inc.php`** — this is the single file for all CC utility functions. Never create separate files for CC utilities.
- **Never store or log raw CC numbers.** Always encrypt with `$GLOBALS['tf']->encrypt($cc)` before storage. Always decrypt with `$GLOBALS['tf']->decrypt($cc)` when reading. Strip credentials from any log data.
- **Always access the global framework via `$GLOBALS['tf']`** — assign `$tf = $GLOBALS['tf'];` at the top of functions that use it repeatedly.
- **Use `get_module_db($module)` for database access** — never use PDO. Query with `$db->query($sql, __LINE__, __FILE__)`. Use `make_insert_query($table, $data)` for inserts.
- **Register every new function in `src/Plugin.php`** inside `getRequirements()` using `$loader->add_requirement('function_name', '/../vendor/detain/myadmin-authorizenet-payments/src/cc.inc.php');`
- **Use `function_requirements('func_name')` before calling other lazy-loaded functions** — this is the plugin system's autoloading mechanism.

## Instructions

1. **Define the function in `src/cc.inc.php`** following the existing docblock pattern:
   ```php
   /**
    * Brief description of what the function does
    *
    * @param type $param description
    * @return type description
    */
   function your_cc_function($param)
   {
       $tf = $GLOBALS['tf'];
       // implementation
   }
   ```
   - Use `snake_case` naming prefixed with `cc_` or descriptive like `get_cc_*`, `mask_cc`, `valid_cc`, `parse_ccs`.
   - Include full PHPDoc with `@param` and `@return` tags.
   - Verify: function is declared at file scope (not inside a class), uses `$GLOBALS['tf']` for encryption/framework access.

2. **Handle CC number sanitization** — strip spaces, underscores, and dashes before processing:
   ```php
   $cc = trim(str_replace([' ', '_', '-'], ['', '', ''], $cc));
   ```
   - For encrypted CC values, decrypt first: `$cc = $tf->decrypt($encrypted_cc);`
   - For storing, encrypt: `$tf->encrypt($clean_cc)`
   - Verify: no raw CC numbers are stored or returned unmasked to the user.

3. **For DB-dependent functions**, follow this pattern:
   ```php
   function get_cc_something()
   {
       $db = $GLOBALS['tf']->db;
       $db->query(
           "SELECT column FROM table WHERE condition",
           __LINE__,
           __FILE__
       );
       while ($db->next_record(MYSQL_ASSOC)) {
           $results[] = $db->Record['column'];
       }
       return $results;
   }
   ```
   - Pass `__LINE__, __FILE__` as second and third args to `$db->query()`.
   - Use `$db->next_record(MYSQL_ASSOC)` and access results via `$db->Record`.
   - Use `$db->real_escape($userInput)` for any user-provided values in queries.
   - Verify: no raw `$_GET`/`$_POST` values interpolated into SQL.

4. **For functions that use account data**, follow the `parse_ccs` / `can_use_cc` pattern:
   ```php
   function my_cc_func($data)
   {
       $tf = $GLOBALS['tf'];
       $ccs = (isset($data['ccs']) ? myadmin_unstringify($data['ccs']) : []);
       // work with $ccs array, each entry has 'cc' (encrypted) and 'cc_exp'
   }
   ```
   - Account data comes from `$tf->accounts->read($account_id)`.
   - The `ccs` field is a JSON-encoded array parsed via `myadmin_unstringify()`.
   - Each CC entry: `['cc' => encrypted_string, 'cc_exp' => 'MM/YYYY', ...]`
   - Verify: function accepts `$data` array, does not call `$tf->accounts->read()` unless it has a `$custid` param.

5. **Add logging with `myadmin_log()`** for significant operations:
   ```php
   myadmin_log('billing', 'debug', 'Description of what happened', __LINE__, __FILE__);
   ```
   - First param: module name (usually `'billing'` or `'myadmin'` for CC functions).
   - Second param: log level — `'debug'`, `'info'`, `'notice'`, `'warning'`.
   - Never log raw CC numbers — use `mask_cc($cc)` in log messages.
   - Verify: no unmasked CC data in log calls.

6. **Register the function in `src/Plugin.php`** in the `getRequirements()` method:
   ```php
   $loader->add_requirement('your_cc_function', '/../vendor/detain/myadmin-authorizenet-payments/src/cc.inc.php');
   ```
   - Use `add_requirement` for functions callable from other modules.
   - Use `add_page_requirement` for functions that render pages/output.
   - Verify: the function name string matches the actual function name exactly.

7. **Write a test in `tests/CcFunctionsTest.php`**:
   - Pure functions (no DB/globals): test directly with assertions.
   - DB-dependent functions: add a static analysis test verifying the function declaration exists:
   ```php
   public function testYourFunctionDeclared(): void
   {
       $content = file_get_contents(self::$sourceFile);
       $this->assertStringContainsString(
           "function your_cc_function(",
           $content,
           "Expected function 'your_cc_function' not found in cc.inc.php"
       );
   }
   ```
   - Follow existing test naming: `testYourFunctionDescriptiveName`.
   - Verify: run `vendor/bin/phpunit tests/CcFunctionsTest.php` and confirm all tests pass.

## Examples

**User says:** "Add a function to get the CC card type from a number"

**Actions taken:**
1. Add to `src/cc.inc.php`:
```php
/**
 * gets the card type/scheme for the given cc number
 *
 * @param string $cc the raw (decrypted) cc number
 * @return string|false the card scheme name or false if unknown
 */
function get_cc_type($cc)
{
    $cc = trim(str_replace([' ', '_', '-'], ['', '', ''], $cc));
    $schemes = [
        'VISA' => ['/^4([0-9]{12}|[0-9]{15})$/'],
        'MASTERCARD' => ['/^5[1-5][0-9]{14}$/', '/^2(22[1-9][0-9]{12}|2[3-9][0-9]{13}|[3-6][0-9]{14}|7[0-1][0-9]{13}|720[0-9]{12})$/'],
        'AMEX' => ['/^3[47]\d{13,14}$/'],
        'DISCOVER' => ['/^6011[0-9]{12}$/', '/^64[4-9][0-9]{13}$/', '/^65[0-9]{14}$/'],
    ];
    foreach ($schemes as $type => $regexes) {
        foreach ($regexes as $regex) {
            if (preg_match($regex, $cc)) {
                return $type;
            }
        }
    }
    return false;
}
```

2. Register in `src/Plugin.php` `getRequirements()`:
```php
$loader->add_requirement('get_cc_type', '/../vendor/detain/myadmin-authorizenet-payments/src/cc.inc.php');
```

3. Add tests to `tests/CcFunctionsTest.php`:
```php
public function testGetCcTypeVisa(): void
{
    $this->assertSame('VISA', get_cc_type('4111111111111111'));
}

public function testGetCcTypeUnknown(): void
{
    $this->assertFalse(get_cc_type('9999999999999999'));
}
```

4. Run tests: `vendor/bin/phpunit tests/CcFunctionsTest.php`

**Result:** New function follows existing `valid_cc` pattern with same regex schemes, registered in plugin loader, tested.

## Common Issues

- **"Call to undefined function function_requirements"**: You're running the function outside the MyAdmin framework. In tests, use `require_once` to load the file directly instead. See `CcFunctionsTest::setUpBeforeClass()` for the pattern.

- **"Call to undefined function myadmin_unstringify"**: The function is in the core framework. In test bootstrap, stub it: `function myadmin_unstringify($data) { return json_decode($data, true) ?? []; }`

- **Encrypted CC returns garbage/empty string**: You're calling `$tf->decrypt()` on an already-decrypted value, or the value was encrypted with a different key. Always check: is the input encrypted or raw? `parse_ccs()` returns decrypted `cc` values in each entry. `$data['cc']` from the accounts table is encrypted.

- **Function not found at runtime but exists in file**: You forgot to register it in `src/Plugin.php` `getRequirements()`. The plugin system only loads functions that are registered via `$loader->add_requirement()`.

- **Test fails with "function mask_cc already defined"**: The `setUpBeforeClass` guard `if (!function_exists('mask_cc'))` prevents double-loading. If adding a new test class that also needs `cc.inc.php`, use the same guard pattern.

- **DB query returns no results but data exists**: Check you're passing `__LINE__, __FILE__` to `$db->query()`. Missing these args can cause silent failures in the DB wrapper. Also verify you're using `MYSQL_ASSOC` in `$db->next_record()`.

- **CC number has spaces/dashes in comparisons**: Always sanitize before comparing: `trim(str_replace([' ', '_', '-'], ['', '', ''], $cc))`. This is done inconsistently in old code — always do it in new functions.