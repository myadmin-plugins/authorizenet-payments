# MyAdmin Authorize.Net Payments Plugin

An Authorize.Net AIM payment gateway plugin for the MyAdmin hosting platform. Handles CC charging, refunds, voids, card verification, and admin management.

## Commands

```bash
composer install                          # install dependencies
vendor/bin/phpunit                        # run all tests
vendor/bin/phpunit --coverage-html coverage/  # HTML coverage report
```

```bash
vendor/bin/phpunit --testsuite Unit           # run unit tests only
vendor/bin/phpunit --filter CcFunctionsTest   # run a specific test class
```

```bash
composer validate                             # validate composer.json
composer dump-autoload                        # rebuild autoloader
```

Config: `phpunit.xml.dist` · Bootstrap: `vendor/autoload.php` · Tests: `tests/` · Coverage: `src/`

## Architecture

**Plugin Entry**: `src/Plugin.php` — `Detain\MyAdminAuthorizenet\Plugin` registers hooks via `getHooks()`:
- `system.settings` → `getSettings()` — registers `authorizenet_enable`, `authorizenet_login`, `authorizenet_password`, `authorizenet_key`, `authorizenet_referrer`
- `function.requirements` → `getRequirements()` — lazy-loads all functions/pages from `src/` files

**Gateway Class**: `src/AuthorizeNetCC.php` — non-namespaced `AuthorizeNetCC` class with `refund()` and `voidTransaction()`. Posts to `https://secure.authorize.net/gateway/transact.dll` via `getcurlpage()`. Logs to `cc_log` table via `make_insert_query()`.

**Core CC Functions** (`src/cc.inc.php`): `mask_cc()` · `valid_cc()` · `charge_card()` (`AUTH_CAPTURE`) · `auth_charge_card()` (`AUTH_ONLY`) · `get_locked_ccs()` · `select_cc_exp()` · `can_use_cc()` · `format_cc_exp()` · `make_cc_decline()` · `email_cc_decline()` · `parse_ccs()` · `get_bad_cc()` · `get_cc_bank_number()` · `get_cc_last_four()`

**Card Management**: `src/manage_cc.php` — customer UI for add/delete/verify/set-primary cards. `src/add_cc.php` — `add_cc()` and `add_cc_new_data()` with rate limiting (`$minimum_days=30`, `$max_early_ccs=4`).

**Verification Flow**: `src/verify_cc.php` — two random sub-$1 `AUTH_ONLY` charges via `verify_cc_charge()`, customer confirms amounts in `verify_cc()`

**Invoice Charging**: `src/charge_card_invoice.php` — charges CC for a specific invoice with CSRF check

**Response Parsing**: `src/get_authorizenet_fields.php` returns field definitions · `src/map_authorizenet_fields.php` uses three strategies: full regex, partial regex, CSV fallback

**Admin Pages** (`src/admin/`):
- `cc_refund.php` — refund/void with `AuthorizeNetCC` class, requires `client_billing` ACL
- `view_cc_transaction.php` — transaction detail view, requires `view_customer` ACL
- `enable_cc.php` · `disable_cc.php` — toggle `disable_cc` flag, requires `edit_customer` ACL
- `authorize_cc.php` — sets `cc_whitelist=1` and `disable_cc=0`
- `enable_cc_whitelist.php` · `disable_cc_whitelist.php` — toggle `cc_whitelist` flag

**Bin Scripts** (`bin/`): `decrypt_ccs.php` · `fix_double_encrypted_ccs.php` · `recrypt_cc.php` · `recrypt_ccs.php` · `recrypt_cc_log.php` · `recrypt_history.php` — CC encryption migration tools. `parse_cc_charges.php` · `rebuild_cc_log.php` · `rebuild_cc_maxlengths.php` · `rebuild_cc_requests.php` · `rebuild_cc_results.php` — log reconstruction utilities.

## Namespace & Autoloading

- `composer.json`: `Detain\MyAdminAuthorizenet\` → `src/` · `Detain\MyAdminAuthorizenet\Tests\` → `tests/`
- `src/Plugin.php` is namespaced; `src/AuthorizeNetCC.php` and procedural files in `src/` are **not** namespaced
- Dependencies: `php >=5.0`, `ext-soap`, `symfony/event-dispatcher ^5.0`, `detain/myadmin-plugin-installer`
- Dev: `phpunit/phpunit ^9.6`

## Coding Conventions

- **DB access**: `$db = get_module_db($module)` or `clone $GLOBALS['tf']->db` — never PDO
- **Inserts**: `make_insert_query($table, $data)` — never manual INSERT strings
- **Escaping**: `$db->real_escape($input)` for user input
- **Logging**: `myadmin_log($module, $level, $message, __LINE__, __FILE__)`
- **Security**: all admin pages check `$GLOBALS['tf']->ima != 'admin'` + `has_acl()` + `verify_csrf_referrer()` or `verify_csrf()`
- **Credentials**: always strip `x_Login`/`x_Password` from log data via `unset($rargs['x_Login'], $rargs['x_Password'])`
- **Encryption**: `$GLOBALS['tf']->encrypt()` / `$GLOBALS['tf']->decrypt()` for CC numbers
- **Function loading**: `function_requirements('function_name')` for lazy-loading
- **Indentation**: tabs, size 4 (per `.scrutinizer.yml`)
- **Commit style**: lowercase, descriptive

## Testing

Tests in `tests/` use PHPUnit 9 with `Detain\MyAdminAuthorizenet\Tests` namespace. Most tests validate source file content via `assertStringContainsString` since functions depend on MyAdmin infrastructure. `CcFunctionsTest` loads `src/cc.inc.php` directly and tests `mask_cc()` and `valid_cc()` with real assertions.

## CI

- `.scrutinizer.yml` — static analysis, coverage via clover
- `.travis.yml` — legacy CI (PHP 5.4–7.1)
- `.codeclimate.yml` · `.bettercodehub.yml` — code quality

<!-- caliber:managed:pre-commit -->
## Before Committing

**IMPORTANT:** Before every git commit, you MUST ensure Caliber syncs agent configs with the latest code changes.

First, check if the pre-commit hook is already installed:
```bash
grep -q "caliber" .git/hooks/pre-commit 2>/dev/null && echo "hook-active" || echo "no-hook"
```

- If **hook-active**: the hook handles sync automatically — just commit normally. Tell the user: "Caliber will sync your agent configs automatically via the pre-commit hook."
- If **no-hook**: run Caliber manually before committing:
  1. Tell the user: "Caliber: Syncing agent configs with your latest changes..."
  2. Run: `caliber refresh && git add CLAUDE.md .claude/ .cursor/ .cursorrules .github/copilot-instructions.md .github/instructions/ AGENTS.md CALIBER_LEARNINGS.md .agents/ .opencode/ 2>/dev/null`
  3. After it completes, briefly tell the user what Caliber updated. Then proceed with the commit.

If `caliber` is not found, tell the user: "This project uses Caliber for agent config sync. Run /setup-caliber to get set up."
<!-- /caliber:managed:pre-commit -->

<!-- caliber:managed:learnings -->
## Session Learnings

Read `CALIBER_LEARNINGS.md` for patterns and anti-patterns learned from previous sessions.
These are auto-extracted from real tool usage — treat them as project-specific rules.
<!-- /caliber:managed:learnings -->

<!-- caliber:managed:sync -->
## Context Sync

This project uses [Caliber](https://github.com/caliber-ai-org/ai-setup) to keep AI agent configs in sync across Claude Code, Cursor, Copilot, and Codex.
Configs update automatically before each commit via `caliber refresh`.
If the pre-commit hook is not set up, run `/setup-caliber` to configure everything automatically.
<!-- /caliber:managed:sync -->
