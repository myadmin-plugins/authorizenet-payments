---
name: admin-page
description: Creates new admin pages following the project's ACL+CSRF pattern in src/admin/. Scaffolds function with page_title(), has_acl() check, verify_csrf_referrer(), and TFTable form output. Use when user says 'new admin page', 'add admin function', or creates files in src/admin/. Do NOT use for client-facing pages or API endpoints.
---
# Admin Page

Create admin pages in `src/admin/` following the exact ACL, CSRF, and output patterns used by existing pages like `src/admin/cc_refund.php` and `src/admin/enable_cc.php`.

## Critical

- Every admin page function MUST call `page_title()` as its first statement.
- Every admin page function MUST load and check ACL: `function_requirements('has_acl');` then `if ($GLOBALS['tf']->ima != 'admin' || !has_acl('ACL_NAME'))` with `dialog()` + `return false;` on failure.
- Valid ACL names used in this project: `client_billing`, `edit_customer`, `view_customer`. Pick the one matching the action's sensitivity.
- Mutating actions (enable/disable/update/refund) MUST verify CSRF via `verify_csrf_referrer(__LINE__, __FILE__)` before performing any writes.
- Forms that POST back to themselves MUST use `$table->csrf('form_name')` to generate the CSRF token, and verify with `verify_csrf('form_name')` on submission.
- Never use PDO. Use `$db = clone $GLOBALS['tf']->db;` for database access.
- Always escape user input with `$db->real_escape()` or cast with `(int)` / `intval()`.
- Use `make_insert_query($table, $data)` for INSERT statements — never build INSERT strings manually.
- Log admin actions with `myadmin_log('admin', 'info', $message, __LINE__, __FILE__);`.

## Instructions

### Step 1: Create the admin page file

Create a new PHP file in `src/admin/`. The filename must match the function name (e.g., `src/admin/enable_cc.php` defines `function enable_cc()`). Use one of these boilerplate patterns based on page type:

**For simple action pages** (toggle a flag, update a field) — see `src/admin/enable_cc.php` for a working example:

```php
<?php
/**
 * Administrative Functionality
 * @author Joe Huss <detain@interserver.net>
 * @copyright 2025
 * @package MyAdmin
 * @category Admin
 */

/**
 * my_admin_action()
 *
 * @return bool|void
 */
function my_admin_action()
{
    page_title('My Admin Action');
    function_requirements('has_acl');
    if ($GLOBALS['tf']->ima != 'admin' || !has_acl('edit_customer')) {
        dialog('Not admin', 'Not Admin or you lack the permissions to view this page.');
        return false;
    }
    if (verify_csrf_referrer(__LINE__, __FILE__)) {
        $module = get_module_name(($GLOBALS['tf']->variables->request['module'] ?? 'default'));
        $customer = $GLOBALS['tf']->variables->request['customer'];
        $data = $GLOBALS['tf']->accounts->read($customer);
        $lid = $data['account_lid'];
        // Perform the action
        $new_data['field_name'] = 1;
        foreach ($GLOBALS['modules'] as $module => $settings) {
            $customer = $GLOBALS['tf']->accounts->cross_reference($lid);
            if ($customer !== false) {
                $GLOBALS['tf']->accounts->update($customer, $new_data);
            }
        }
        add_output('Action completed successfully.');
        myadmin_log('admin', 'info', "Performed action for {$lid}", __LINE__, __FILE__);
    }
}
```

**For form-based pages** (confirmation dialogs, multi-step workflows) — see `src/admin/cc_refund.php` for a working example:

```php
<?php
/**
 * @return bool|void
 * @throws \Exception
 * @throws \SmartyException
 */
function my_form_action()
{
    page_title('My Form Action');
    function_requirements('has_acl');
    if ($GLOBALS['tf']->ima != 'admin' || !has_acl('client_billing')) {
        dialog('Not admin', 'Not Admin or you lack the permissions to view this page.');
        return false;
    }
    // Validate required parameters
    if (!isset($GLOBALS['tf']->variables->request['id'])) {
        add_output('ID is empty!');
        return;
    }
    $id = (int)$GLOBALS['tf']->variables->request['id'];
    $db = clone $GLOBALS['tf']->db;
    // Phase 1: Show confirmation form
    if (!isset($GLOBALS['tf']->variables->request['confirmed']) || !verify_csrf('my_form_action')) {
        $table = new TFTable();
        $table->csrf('my_form_action');
        $table->set_title('Confirm Action');
        $table->set_post_location('index.php');
        $table->add_hidden('id', $id);
        // Add form fields
        $table->add_field('Amount', 'l');
        $table->add_field($table->make_input('amount', '0.00', 25), 'l');
        $table->add_row();
        $table->add_hidden('confirmed', 'yes');
        $table->set_colspan(2);
        $table->add_field($table->make_submit('Submit'));
        $table->add_row();
        add_output($table->get_table());
    // Phase 2: Process confirmed action
    } elseif (isset($GLOBALS['tf']->variables->request['confirmed']) && $GLOBALS['tf']->variables->request['confirmed'] == 'yes') {
        // Validate inputs
        // Perform action
        // Log result
        myadmin_log('admin', 'info', 'Processed form action', __LINE__, __FILE__);
        add_output('Action completed.');
    }
}
```

Verify the file exists and the function is defined:

```bash
grep -c 'function my_admin_action' src/admin/my_admin_action.php
```

### Step 2: Register the page in Plugin.php

Open `src/Plugin.php` and add a `$loader->add_page_requirement()` line inside the `getRequirements()` method. Follow the pattern used by existing registrations like `cc_refund`:

```php
$loader->add_page_requirement('cc_refund', '/../vendor/detain/myadmin-authorizenet-payments/src/admin/cc_refund.php');
```

Use `add_page_requirement` (not `add_requirement`) for functions that serve as standalone pages. Use `add_requirement` only for utility functions called by other code.

Verify the registration was added correctly:

```bash
grep 'add_page_requirement' src/Plugin.php
```

### Step 3: Add a test (optional but recommended)

If adding a non-trivial page, add a test in `tests/` following the existing pattern. Existing tests mock `$GLOBALS['tf']` and verify function existence.

Verify tests pass:

```bash
vendor/bin/phpunit
```

## Examples

### Example: Simple toggle page

**User says:** "Create an admin page to lock a customer's CC transactions"

**Actions taken:**
1. Create `src/admin/lock_cc_transactions.php` with the simple action pattern:
   - `page_title('Lock CC Transactions')`
   - ACL check with `edit_customer`
   - `verify_csrf_referrer(__LINE__, __FILE__)`
   - Set `$new_data['cc_locked'] = 1` and update across modules
   - `add_output('CC Transactions Locked')`
   - `myadmin_log('admin', 'info', ...)`
2. Add to `src/Plugin.php` `getRequirements()`:
   ```php
   $loader->add_page_requirement('lock_cc_transactions', '/../vendor/detain/myadmin-authorizenet-payments/src/admin/lock_cc_transactions.php');
   ```
3. Run `vendor/bin/phpunit` to verify no breakage.

**Result:** New admin page accessible at `index.php?choice=none.lock_cc_transactions&customer=123&module=default` with CSRF protection and ACL gating.

### Example: Form-based page with confirmation

**User says:** "Create an admin page to adjust a CC transaction amount"

**Actions taken:**
1. Create `src/admin/adjust_cc_amount.php` with the form-based pattern:
   - Phase 1: TFTable form with `$table->csrf('adjust_cc_amount')`, hidden `transact_id`, input for new amount
   - Phase 2: `verify_csrf('adjust_cc_amount')`, validate amount > 0, perform DB update with `make_insert_query()` for history log, `myadmin_log()`
   - ACL check with `client_billing` (financial operation)
2. Register in `src/Plugin.php`.
3. Run tests.

## Common Issues

### "Not Admin or you lack the permissions to view this page" when testing
1. Confirm `$GLOBALS['tf']->ima` is set to `'admin'` in your test environment.
2. Confirm the ACL name you used (e.g., `client_billing`, `edit_customer`, `view_customer`) is assigned to the test admin user. Check with: `has_acl('your_acl_name')`.
3. The ACL check pattern must be `$GLOBALS['tf']->ima != 'admin' || !has_acl(...)` — both conditions joined with `||`, not `&&`.

### CSRF verification fails silently (page does nothing on submit)
1. For simple action pages: ensure the link/form that triggers the page includes a valid CSRF referrer. The `verify_csrf_referrer(__LINE__, __FILE__)` checks the HTTP referrer against allowed origins.
2. For form-based pages: ensure `$table->csrf('form_name')` in the form matches `verify_csrf('form_name')` in the handler. These strings must be identical.
3. Check that `$table->set_post_location('index.php')` is set — without it the form may POST to the wrong URL.

### Function not found / page not loading
1. Verify `src/Plugin.php` has `$loader->add_page_requirement('function_name', ...)` — the first argument must exactly match the function name defined in the PHP file.
2. Verify the path in `add_page_requirement` follows the pattern seen in existing registrations in `src/Plugin.php`.
3. The function name and filename must match: `function foo_bar()` lives in `foo_bar.php`.

### TFTable form not rendering
1. You must call `$table = new TFTable();` (capital T in TFTable, though `TFtable` also works — both are seen in the codebase).
2. Every `add_field()` call must be followed by `add_row()` to commit the row.
3. Call `add_output($table->get_table())` to render — do not `echo` or `return` the table.

### Database queries return no results unexpectedly
1. Use `$db = clone $GLOBALS['tf']->db;` — do NOT use `$GLOBALS['tf']->db` directly, as it shares state.
2. If you need multiple concurrent queries, clone separately: `$db = clone $GLOBALS['tf']->db; $db2 = clone $GLOBALS['tf']->db;`.
3. After `$db->query(...)`, check `$db->num_rows()` before calling `$db->next_record(MYSQL_ASSOC)`. Access results via `$db->Record`.