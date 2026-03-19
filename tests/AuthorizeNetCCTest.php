<?php

declare(strict_types=1);

namespace Detain\MyAdminAuthorizenet\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for the AuthorizeNetCC class.
 *
 * Since AuthorizeNetCC is a global (non-namespaced) class that depends on
 * AUTHORIZENET_LOGIN and AUTHORIZENET_PASSWORD constants at property
 * initialization time, these tests use reflection and static analysis
 * to verify class structure without instantiation.
 */
class AuthorizeNetCCTest extends TestCase
{
    private static string $sourceFile;

    public static function setUpBeforeClass(): void
    {
        self::$sourceFile = dirname(__DIR__) . '/src/AuthorizeNetCC.php';
    }

    /**
     * Tests that the AuthorizeNetCC source file exists.
     *
     * This file contains the credit card processing class that interfaces
     * with the Authorize.Net payment gateway.
     */
    public function testSourceFileExists(): void
    {
        $this->assertFileExists(self::$sourceFile);
    }

    /**
     * Tests that the source file declares the AuthorizeNetCC class.
     *
     * Uses static analysis of the file contents to verify class declaration
     * without loading it (which requires constants to be defined).
     */
    public function testSourceFileDeclaresClass(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString('class AuthorizeNetCC', $content);
    }

    /**
     * Tests that the class contains a refund method.
     *
     * The refund method handles credit card refunds for transactions
     * that are less than 120 days old.
     */
    public function testSourceFileContainsRefundMethod(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString('public function refund(', $content);
    }

    /**
     * Tests that the class contains a voidTransaction method.
     *
     * The voidTransaction method handles voiding credit card transactions.
     */
    public function testSourceFileContainsVoidTransactionMethod(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString('public function voidTransaction(', $content);
    }

    /**
     * Tests that the refund method accepts the expected parameters.
     *
     * Validates the method signature: cc_num, trans_id, amount, custid.
     */
    public function testRefundMethodSignature(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertMatchesRegularExpression(
            '/public function refund\(\$cc_num,\s*\$trans_id,\s*\$amount,\s*\$custid\)/',
            $content
        );
    }

    /**
     * Tests that the voidTransaction method accepts the expected parameters.
     *
     * Validates the method signature: trans_id, cc_num, custid.
     */
    public function testVoidTransactionMethodSignature(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertMatchesRegularExpression(
            '/public function voidTransaction\(\$trans_id,\s*\$cc_num,\s*\$custid\)/',
            $content
        );
    }

    /**
     * Tests that the class has a private Auth_Data property.
     *
     * Auth_Data stores the Authorize.Net API credentials and settings.
     */
    public function testSourceFileContainsAuthDataProperty(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString("private \$Auth_Data", $content);
    }

    /**
     * Tests that Auth_Data contains the expected API version.
     *
     * The Authorize.Net API version 3.1 is used for the AIM integration.
     */
    public function testAuthDataContainsApiVersion(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString("'x_Version' => '3.1'", $content);
    }

    /**
     * Tests that Auth_Data enables delimited data responses.
     *
     * x_Delim_Data must be TRUE for CSV-formatted responses.
     */
    public function testAuthDataContainsDelimData(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString("'x_Delim_Data' => 'TRUE'", $content);
    }

    /**
     * Tests that Auth_Data uses quote as the encapsulation character.
     *
     * x_Encap_Char wraps each field in the response for proper CSV parsing.
     */
    public function testAuthDataContainsEncapChar(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString("'x_Encap_Char' => '\"'", $content);
    }

    /**
     * Tests that the refund method validates empty credit card numbers.
     *
     * The method should return an error string if cc_num is empty/falsy.
     */
    public function testRefundValidatesEmptyCcNum(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString("'Error! Credit card Number is empty'", $content);
    }

    /**
     * Tests that the refund method validates empty transaction IDs.
     *
     * The method should return an error string if trans_id is empty/falsy.
     */
    public function testRefundValidatesEmptyTransId(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString("'Error! Transaction id is empty'", $content);
    }

    /**
     * Tests that the refund method validates empty amounts.
     *
     * The method should return an error string if amount is empty/falsy.
     */
    public function testRefundValidatesEmptyAmount(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString("'Error! Amount is empty'", $content);
    }

    /**
     * Tests that the refund method uses CREDIT transaction type.
     *
     * CREDIT is the Authorize.Net type for processing refunds.
     */
    public function testRefundUsesCreditType(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString("'x_type'] = 'CREDIT'", $content);
    }

    /**
     * Tests that the voidTransaction method uses Void transaction type.
     *
     * Void is the Authorize.Net type for voiding pending transactions.
     */
    public function testVoidUsesVoidType(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString("'x_type'] = 'Void'", $content);
    }

    /**
     * Tests that both methods communicate with the correct Authorize.Net URL.
     *
     * The gateway endpoint is the AIM (Advanced Integration Method) URL.
     */
    public function testUsesCorrectGatewayUrl(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString('https://secure.authorize.net/gateway/transact.dll', $content);
    }

    /**
     * Tests that the refund method sets the duplicate window to 0.
     *
     * This prevents the gateway from rejecting the refund as a duplicate
     * of the original transaction.
     */
    public function testRefundSetsDuplicateWindowZero(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString("'x_duplicate_window'] = '0'", $content);
    }

    /**
     * Tests that the response field mapping contains essential fields.
     *
     * The fields array maps CSV response positions to named fields
     * for structured data access.
     */
    public function testResponseFieldMappingContainsEssentialFields(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString("'code'", $content);
        $this->assertStringContainsString("'reason_text'", $content);
        $this->assertStringContainsString("'trans_id'", $content);
        $this->assertStringContainsString("'auth_code'", $content);
        $this->assertStringContainsString("'account_num'", $content);
        $this->assertStringContainsString("'card_type'", $content);
    }

    /**
     * Tests that sensitive credentials are stripped from logged data.
     *
     * The code must unset x_Login and x_Password from the args before logging
     * to prevent credential leakage.
     */
    public function testCredentialsStrippedFromLogData(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString("unset(\$rargs['x_Login'], \$rargs['x_Password']", $content);
    }

    /**
     * Tests that the class is not namespaced (legacy global class).
     *
     * AuthorizeNetCC is a global class, unlike Plugin which is namespaced.
     * This is important for how it's loaded via function_requirements.
     */
    public function testClassIsNotNamespaced(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringNotContainsString('namespace ', $content);
    }
}
