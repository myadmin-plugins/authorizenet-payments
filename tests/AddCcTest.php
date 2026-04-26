<?php

declare(strict_types=1);

namespace Detain\MyAdminAuthorizenet\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests for functions in add_cc.php via static analysis.
 *
 * The add_cc and add_cc_new_data functions handle adding new credit cards
 * to customer accounts. They depend on global state and external services,
 * so we test structure and behavior patterns through source code analysis.
 */
class AddCcTest extends TestCase
{
    private static string $sourceFile;

    public static function setUpBeforeClass(): void
    {
        self::$sourceFile = dirname(__DIR__) . '/src/add_cc.php';
    }

    /**
     * Tests that the add_cc function is declared.
     *
     * This is the main function for adding credit cards to customer accounts.
     */
    public function testAddCcFunctionDeclared(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString('function add_cc(', $content);
    }

    /**
     * Tests that the add_cc_new_data function is declared.
     *
     * This helper function updates account data with new CC information.
     */
    public function testAddCcNewDataFunctionDeclared(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString('function add_cc_new_data(', $content);
    }

    /**
     * Tests that add_cc validates card format using valid_cc.
     *
     * The function must verify the card number format before adding it.
     */
    public function testAddCcValidatesCardFormat(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString('valid_cc(', $content);
        $this->assertStringContainsString('Invalid card format', $content);
    }

    /**
     * Tests that add_cc enforces rate limits for new accounts.
     *
     * New accounts (under 30 days old) are limited to 4 credit cards
     * to prevent abuse.
     */
    public function testAddCcEnforcesRateLimits(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString('$minimum_days = 30', $content);
        $this->assertStringContainsString('$max_early_ccs = 4', $content);
    }

    /**
     * Tests that add_cc returns a structured result array.
     *
     * The function returns an array with idx, status, text, and data keys.
     */
    public function testAddCcReturnsStructuredResult(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString("'idx' => ''", $content);
        $this->assertStringContainsString("'status' => ''", $content);
        $this->assertStringContainsString("'text' => ''", $content);
        $this->assertStringContainsString("'data' => \$data", $content);
    }

    /**
     * Tests that add_cc supports both ok and verify statuses.
     *
     * Cards that pass can_use_cc get 'ok' status; others need 'verify'.
     */
    public function testAddCcSupportsExpectedStatuses(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString("'status'] = 'ok'", $content);
        $this->assertStringContainsString("'status'] = 'verify'", $content);
        $this->assertStringContainsString("'status'] = 'error'", $content);
    }

    /**
     * Tests that add_cc encrypts the card number before storage.
     *
     * Credit card numbers must be encrypted using the framework's
     * encrypt function before being stored in the database.
     */
    public function testAddCcEncryptsCardNumber(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString('\MyAdmin\App::encrypt(', $content);
    }

    /**
     * Tests that add_cc strips formatting characters from card numbers.
     *
     * Spaces, underscores, and dashes should be removed from the
     * card number input.
     */
    public function testAddCcStripsFormattingCharacters(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString("str_replace([' ', '_', '-'], ['', '', '']", $content);
    }

    /**
     * Tests that add_cc handles 4-digit expiration format.
     *
     * If the expiration is entered as MMYY (4 digits), it should be
     * converted to MM/20YY format.
     */
    public function testAddCcHandlesFourDigitExpiration(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString("'/^[0-9][0-9][0-9][0-9]$/'", $content);
    }

    /**
     * Tests that add_cc_new_data updates billing address fields.
     *
     * When a credit card includes address data, the fields (name, address,
     * city, state, zip, country) should be stored with the account.
     */
    public function testAddCcNewDataUpdatesAddressFields(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString("'name', 'address', 'city', 'state', 'zip', 'country'", $content);
    }

    /**
     * Tests that add_cc triggers fraud protection for new cards.
     *
     * When maxmind or fraudrecord scores are not set, the function
     * triggers fraud checks.
     */
    public function testAddCcTriggersFraudProtection(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString('update_maxmind', $content);
        $this->assertStringContainsString('update_fraudrecord', $content);
    }

    /**
     * Tests that add_cc supports the force parameter.
     *
     * Admins can force-add a credit card, bypassing can_use_cc checks.
     */
    public function testAddCcSupportsForceParameter(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString('$force = false', $content);
        $this->assertStringContainsString('$force !== true', $content);
    }
}
