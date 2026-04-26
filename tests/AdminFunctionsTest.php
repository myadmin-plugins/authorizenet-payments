<?php

declare(strict_types=1);

namespace Detain\MyAdminAuthorizenet\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests for admin functions via static analysis.
 *
 * The admin functions (disable_cc, enable_cc, authorize_cc, etc.) all
 * depend heavily on global state ($GLOBALS['tf'], database connections,
 * ACL checks). These tests verify their structure and security patterns
 * through source code analysis.
 */
class AdminFunctionsTest extends TestCase
{
    private static string $adminDir;

    public static function setUpBeforeClass(): void
    {
        self::$adminDir = dirname(__DIR__) . '/src/admin';
    }

    // ==================== disable_cc ====================

    /**
     * Tests that disable_cc checks for admin role.
     *
     * The function must verify the user is an admin before performing
     * any operations, as this is a privileged administrative action.
     */
    public function testDisableCcChecksAdminRole(): void
    {
        $content = file_get_contents(self::$adminDir . '/disable_cc.php');
        $this->assertStringContainsString("\\MyAdmin\App::ima() != 'admin'", $content);
    }

    /**
     * Tests that disable_cc checks edit_customer ACL permission.
     *
     * Disabling a customer's credit card requires edit_customer permission.
     */
    public function testDisableCcChecksAcl(): void
    {
        $content = file_get_contents(self::$adminDir . '/disable_cc.php');
        $this->assertStringContainsString("has_acl('edit_customer')", $content);
    }

    /**
     * Tests that disable_cc verifies CSRF token.
     *
     * All state-changing admin actions must validate the CSRF referrer
     * to prevent cross-site request forgery attacks.
     */
    public function testDisableCcVerifiesCsrf(): void
    {
        $content = file_get_contents(self::$adminDir . '/disable_cc.php');
        $this->assertStringContainsString('verify_csrf_referrer(', $content);
    }

    /**
     * Tests that disable_cc sets disable_cc to 1.
     *
     * The function must set the disable_cc flag to 1 (disabled).
     */
    public function testDisableCcSetsFlag(): void
    {
        $content = file_get_contents(self::$adminDir . '/disable_cc.php');
        $this->assertStringContainsString("'disable_cc'] = 1", $content);
    }

    /**
     * Tests that disable_cc switches payment method to paypal.
     *
     * When CC is disabled, the payment method must fall back to PayPal.
     */
    public function testDisableCcSwitchesToPaypal(): void
    {
        $content = file_get_contents(self::$adminDir . '/disable_cc.php');
        $this->assertStringContainsString("'payment_method'] = 'paypal'", $content);
    }

    // ==================== enable_cc ====================

    /**
     * Tests that enable_cc checks for admin role.
     *
     * Enabling credit cards is a privileged action requiring admin access.
     */
    public function testEnableCcChecksAdminRole(): void
    {
        $content = file_get_contents(self::$adminDir . '/enable_cc.php');
        $this->assertStringContainsString("\\MyAdmin\App::ima() != 'admin'", $content);
    }

    /**
     * Tests that enable_cc checks edit_customer ACL.
     *
     * The edit_customer permission is required to modify customer settings.
     */
    public function testEnableCcChecksAcl(): void
    {
        $content = file_get_contents(self::$adminDir . '/enable_cc.php');
        $this->assertStringContainsString("has_acl('edit_customer')", $content);
    }

    /**
     * Tests that enable_cc sets disable_cc to 0.
     *
     * The function must clear the disable_cc flag (set to 0) to re-enable.
     */
    public function testEnableCcClearsFlag(): void
    {
        $content = file_get_contents(self::$adminDir . '/enable_cc.php');
        $this->assertStringContainsString("'disable_cc'] = 0", $content);
    }

    // ==================== authorize_cc ====================

    /**
     * Tests that authorize_cc sets both disable_cc and cc_whitelist.
     *
     * Authorizing a customer both enables their credit card and adds
     * them to the whitelist for immediate use.
     */
    public function testAuthorizeCcSetsBothFlags(): void
    {
        $content = file_get_contents(self::$adminDir . '/authorize_cc.php');
        $this->assertStringContainsString("'disable_cc'] = 0", $content);
        $this->assertStringContainsString("'cc_whitelist'] = 1", $content);
    }

    /**
     * Tests that authorize_cc checks admin role and ACL.
     *
     * Authorization is a privileged action requiring both admin status
     * and edit_customer permission.
     */
    public function testAuthorizeCcChecksPermissions(): void
    {
        $content = file_get_contents(self::$adminDir . '/authorize_cc.php');
        $this->assertStringContainsString("\\MyAdmin\App::ima() != 'admin'", $content);
        $this->assertStringContainsString("has_acl('edit_customer')", $content);
    }

    // ==================== enable_cc_whitelist ====================

    /**
     * Tests that enable_cc_whitelist sets whitelist flag to 1.
     *
     * Adding a customer to the whitelist bypasses risk score checks.
     */
    public function testEnableCcWhitelistSetsFlag(): void
    {
        $content = file_get_contents(self::$adminDir . '/enable_cc_whitelist.php');
        $this->assertStringContainsString("'cc_whitelist'] = 1", $content);
    }

    /**
     * Tests that enable_cc_whitelist checks admin role and ACL.
     *
     * Whitelist management is restricted to admins with edit_customer permission.
     */
    public function testEnableCcWhitelistChecksPermissions(): void
    {
        $content = file_get_contents(self::$adminDir . '/enable_cc_whitelist.php');
        $this->assertStringContainsString("\\MyAdmin\App::ima() != 'admin'", $content);
        $this->assertStringContainsString("has_acl('edit_customer')", $content);
    }

    // ==================== disable_cc_whitelist ====================

    /**
     * Tests that disable_cc_whitelist sets whitelist flag to 0.
     *
     * Removing from the whitelist re-enables risk score checks for the customer.
     */
    public function testDisableCcWhitelistClearsFlag(): void
    {
        $content = file_get_contents(self::$adminDir . '/disable_cc_whitelist.php');
        $this->assertStringContainsString("'cc_whitelist'] = 0", $content);
    }

    // ==================== cc_refund ====================

    /**
     * Tests that cc_refund checks for admin role and client_billing ACL.
     *
     * Refund processing requires admin access with billing permissions.
     */
    public function testCcRefundChecksPermissions(): void
    {
        $content = file_get_contents(self::$adminDir . '/cc_refund.php');
        $this->assertStringContainsString("\\MyAdmin\App::ima() != 'admin'", $content);
        $this->assertStringContainsString("has_acl('client_billing')", $content);
    }

    /**
     * Tests that cc_refund validates transaction ID presence.
     *
     * The function must check that a transaction ID was provided before
     * attempting to process the refund.
     */
    public function testCcRefundValidatesTransactionId(): void
    {
        $content = file_get_contents(self::$adminDir . '/cc_refund.php');
        $this->assertStringContainsString("'transact_id'", $content);
        $this->assertStringContainsString('Transaction ID is empty', $content);
    }

    /**
     * Tests that cc_refund supports both void and refund operations.
     *
     * Same-day transactions are voided; older ones are refunded.
     */
    public function testCcRefundSupportsVoidAndRefund(): void
    {
        $content = file_get_contents(self::$adminDir . '/cc_refund.php');
        $this->assertStringContainsString("'void'", $content);
        $this->assertStringContainsString("'refund'", $content);
        $this->assertStringContainsString('voidTransaction(', $content);
        $this->assertStringContainsString('->refund(', $content);
    }

    /**
     * Tests that cc_refund uses CSRF protection.
     *
     * The refund confirmation form must be protected against CSRF.
     */
    public function testCcRefundUsesCsrf(): void
    {
        $content = file_get_contents(self::$adminDir . '/cc_refund.php');
        $this->assertStringContainsString("verify_csrf('cc_refund')", $content);
    }

    /**
     * Tests that cc_refund validates refund amount against paid amount.
     *
     * The refund amount must not exceed the original transaction amount.
     */
    public function testCcRefundValidatesAmount(): void
    {
        $content = file_get_contents(self::$adminDir . '/cc_refund.php');
        $this->assertStringContainsString('Refund amount greater than paid amount', $content);
    }

    // ==================== view_cc_transaction ====================

    /**
     * Tests that view_cc_transaction declares the get_cc_cats_and_fields function.
     *
     * This helper function provides category and field metadata for the
     * transaction detail view template.
     */
    public function testViewCcTransactionDeclaresHelperFunction(): void
    {
        $content = file_get_contents(self::$adminDir . '/view_cc_transaction.php');
        $this->assertStringContainsString('function get_cc_cats_and_fields()', $content);
    }

    /**
     * Tests that view_cc_transaction supports both ID and transaction lookups.
     *
     * The function can look up transactions by database ID or by
     * Authorize.Net transaction ID.
     */
    public function testViewCcTransactionSupportsMultipleLookups(): void
    {
        $content = file_get_contents(self::$adminDir . '/view_cc_transaction.php');
        $this->assertStringContainsString("request['id']", $content);
        $this->assertStringContainsString("request['transaction']", $content);
    }

    /**
     * Tests that view_cc_transaction restricts client access.
     *
     * Clients should only see their own transactions; admins need
     * view_customer ACL permission.
     */
    public function testViewCcTransactionRestrictsAccess(): void
    {
        $content = file_get_contents(self::$adminDir . '/view_cc_transaction.php');
        $this->assertStringContainsString("has_acl('view_customer')", $content);
        $this->assertStringContainsString("CLIENT_VIEW_PAYMENT", $content);
    }
}
