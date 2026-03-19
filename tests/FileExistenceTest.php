<?php

declare(strict_types=1);

namespace Detain\MyAdminAuthorizenet\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests that all expected source files exist in the package.
 *
 * This ensures the package is complete and no files have been
 * accidentally deleted or moved. Each file provides specific
 * functionality for the Authorize.Net payment processing.
 */
class FileExistenceTest extends TestCase
{
    private static string $srcDir;

    public static function setUpBeforeClass(): void
    {
        self::$srcDir = dirname(__DIR__) . '/src';
    }

    /**
     * Tests that the Plugin class file exists.
     *
     * Plugin.php is the main entry point for the MyAdmin plugin system
     * and registers event hooks and settings.
     */
    public function testPluginFileExists(): void
    {
        $this->assertFileExists(self::$srcDir . '/Plugin.php');
    }

    /**
     * Tests that the AuthorizeNetCC class file exists.
     *
     * Contains the refund and void transaction methods for direct
     * Authorize.Net API interaction.
     */
    public function testAuthorizeNetCCFileExists(): void
    {
        $this->assertFileExists(self::$srcDir . '/AuthorizeNetCC.php');
    }

    /**
     * Tests that the credit card functions file exists.
     *
     * cc.inc.php contains core billing utilities like mask_cc, valid_cc,
     * charge_card, and auth_charge_card.
     */
    public function testCcIncFileExists(): void
    {
        $this->assertFileExists(self::$srcDir . '/cc.inc.php');
    }

    /**
     * Tests that the add credit card file exists.
     *
     * Handles adding new credit cards to customer accounts.
     */
    public function testAddCcFileExists(): void
    {
        $this->assertFileExists(self::$srcDir . '/add_cc.php');
    }

    /**
     * Tests that the charge card invoice file exists.
     *
     * Provides the page handler for charging invoices to credit cards.
     */
    public function testChargeCardInvoiceFileExists(): void
    {
        $this->assertFileExists(self::$srcDir . '/charge_card_invoice.php');
    }

    /**
     * Tests that the get authorizenet fields file exists.
     *
     * Defines the metadata for all Authorize.Net CSV response fields.
     */
    public function testGetAuthorizenetFieldsFileExists(): void
    {
        $this->assertFileExists(self::$srcDir . '/get_authorizenet_fields.php');
    }

    /**
     * Tests that the manage credit cards file exists.
     *
     * Provides the UI page for customers to manage their credit cards.
     */
    public function testManageCcFileExists(): void
    {
        $this->assertFileExists(self::$srcDir . '/manage_cc.php');
    }

    /**
     * Tests that the map authorizenet fields file exists.
     *
     * Maps raw CSV response data to named field arrays.
     */
    public function testMapAuthorizenetFieldsFileExists(): void
    {
        $this->assertFileExists(self::$srcDir . '/map_authorizenet_fields.php');
    }

    /**
     * Tests that the verify credit card file exists.
     *
     * Handles the two-charge verification process for new credit cards.
     */
    public function testVerifyCcFileExists(): void
    {
        $this->assertFileExists(self::$srcDir . '/verify_cc.php');
    }

    /**
     * Tests that the admin view_cc_transaction file exists.
     *
     * Provides the admin interface for viewing credit card transaction details.
     */
    public function testAdminViewCcTransactionFileExists(): void
    {
        $this->assertFileExists(self::$srcDir . '/admin/view_cc_transaction.php');
    }

    /**
     * Tests that the admin disable_cc file exists.
     *
     * Admin function to disable credit card billing for a customer.
     */
    public function testAdminDisableCcFileExists(): void
    {
        $this->assertFileExists(self::$srcDir . '/admin/disable_cc.php');
    }

    /**
     * Tests that the admin enable_cc file exists.
     *
     * Admin function to enable credit card billing for a customer.
     */
    public function testAdminEnableCcFileExists(): void
    {
        $this->assertFileExists(self::$srcDir . '/admin/enable_cc.php');
    }

    /**
     * Tests that the admin authorize_cc file exists.
     *
     * Admin function to authorize and whitelist a customer for credit card use.
     */
    public function testAdminAuthorizeCcFileExists(): void
    {
        $this->assertFileExists(self::$srcDir . '/admin/authorize_cc.php');
    }

    /**
     * Tests that the admin cc_refund file exists.
     *
     * Admin function to process credit card refunds and voids.
     */
    public function testAdminCcRefundFileExists(): void
    {
        $this->assertFileExists(self::$srcDir . '/admin/cc_refund.php');
    }

    /**
     * Tests that the admin disable_cc_whitelist file exists.
     *
     * Admin function to remove a customer from the credit card whitelist.
     */
    public function testAdminDisableCcWhitelistFileExists(): void
    {
        $this->assertFileExists(self::$srcDir . '/admin/disable_cc_whitelist.php');
    }

    /**
     * Tests that the admin enable_cc_whitelist file exists.
     *
     * Admin function to add a customer to the credit card whitelist.
     */
    public function testAdminEnableCcWhitelistFileExists(): void
    {
        $this->assertFileExists(self::$srcDir . '/admin/enable_cc_whitelist.php');
    }

    /**
     * Tests that composer.json exists in the package root.
     *
     * Required for Composer autoloading and dependency management.
     */
    public function testComposerJsonExists(): void
    {
        $this->assertFileExists(dirname(__DIR__) . '/composer.json');
    }
}
