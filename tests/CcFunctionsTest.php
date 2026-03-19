<?php

declare(strict_types=1);

namespace Detain\MyAdminAuthorizenet\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests for functions defined in cc.inc.php.
 *
 * The functions mask_cc and valid_cc are pure functions that can be tested
 * directly. Other functions in this file depend heavily on global state
 * and database access, so those are tested via static analysis.
 */
class CcFunctionsTest extends TestCase
{
    private static string $sourceFile;

    public static function setUpBeforeClass(): void
    {
        self::$sourceFile = dirname(__DIR__) . '/src/cc.inc.php';
        if (!function_exists('mask_cc')) {
            // Define stubs for functions used inside cc.inc.php but not tested here
            if (!function_exists('myadmin_unstringify')) {
                function myadmin_unstringify($data) { return json_decode($data, true) ?? []; }
            }
            require_once self::$sourceFile;
        }
    }

    // ==================== mask_cc tests ====================

    /**
     * Tests masking a standard 16-digit credit card number showing last 4 digits.
     *
     * This is the most common use case: masking a full Visa/MC number
     * to show only the last four digits for display purposes.
     */
    public function testMaskCcShowsLastFourByDefault(): void
    {
        $result = mask_cc('4111111111111111');
        $this->assertSame('************1111', $result);
    }

    /**
     * Tests masking a credit card showing the first 4 digits instead.
     *
     * When $last is false, the function masks the trailing digits
     * and shows the first four (the BIN/IIN range).
     */
    public function testMaskCcShowsFirstFourWhenLastIsFalse(): void
    {
        $result = mask_cc('4111111111111111', false);
        $this->assertSame('4111************', $result);
    }

    /**
     * Tests that short credit card numbers (6 chars or less) are returned as-is.
     *
     * Numbers too short to meaningfully mask are returned unmodified.
     * This handles edge cases like last-four-only values.
     */
    public function testMaskCcReturnsShortNumbersUnchanged(): void
    {
        $this->assertSame('1234', mask_cc('1234'));
        $this->assertSame('123456', mask_cc('123456'));
    }

    /**
     * Tests masking a 7-digit number (minimum length for masking).
     *
     * With 7 digits, 3 should be masked and 4 shown at the end.
     */
    public function testMaskCcSevenDigitNumber(): void
    {
        $result = mask_cc('1234567');
        $this->assertSame('***4567', $result);
    }

    /**
     * Tests masking an AMEX card number (15 digits).
     *
     * AMEX cards have 15 digits, so 11 should be masked.
     */
    public function testMaskCcAmexNumber(): void
    {
        $result = mask_cc('378282246310005');
        $this->assertSame('***********0005', $result);
    }

    /**
     * Tests masking a 15-digit AMEX showing first four.
     *
     * When showing the first four of an AMEX card.
     */
    public function testMaskCcAmexShowFirst(): void
    {
        $result = mask_cc('378282246310005', false);
        $this->assertSame('3782***********', $result);
    }

    // ==================== valid_cc tests ====================

    /**
     * Tests that a standard Visa card number is recognized as valid.
     *
     * Visa cards start with 4 and have 13 or 16 digits.
     */
    public function testValidCcVisaCard(): void
    {
        $this->assertTrue(valid_cc('4111111111111111'));
    }

    /**
     * Tests that a standard MasterCard number is recognized as valid.
     *
     * MasterCard numbers start with 51-55 and have 16 digits.
     */
    public function testValidCcMasterCard(): void
    {
        $this->assertTrue(valid_cc('5500000000000004'));
    }

    /**
     * Tests that an AMEX card number is recognized as valid.
     *
     * AMEX cards start with 34 or 37 and have 15 digits.
     */
    public function testValidCcAmex(): void
    {
        $this->assertTrue(valid_cc('378282246310005'));
    }

    /**
     * Tests that a Discover card number is recognized as valid.
     *
     * Discover cards start with 6011 and have 16 digits.
     */
    public function testValidCcDiscover(): void
    {
        $this->assertTrue(valid_cc('6011111111111117'));
    }

    /**
     * Tests that a Diners Club card number is recognized as valid.
     *
     * Diners Club cards start with 300-305 or 36/38 and have 14 digits.
     */
    public function testValidCcDinersClub(): void
    {
        $this->assertTrue(valid_cc('30569309025904'));
    }

    /**
     * Tests that a JCB card number is recognized as valid.
     *
     * JCB cards start with 2131, 1800, or 35 and have 15-16 digits.
     */
    public function testValidCcJcb(): void
    {
        $this->assertTrue(valid_cc('3530111333300000'));
    }

    /**
     * Tests that an obviously invalid number is rejected.
     *
     * A random string of digits that does not match any card scheme
     * should return false.
     */
    public function testValidCcRejectsInvalidNumber(): void
    {
        $this->assertFalse(valid_cc('1234567890123456'));
    }

    /**
     * Tests that an empty string is rejected.
     *
     * An empty string cannot be a valid credit card number.
     */
    public function testValidCcRejectsEmptyString(): void
    {
        $this->assertFalse(valid_cc(''));
    }

    /**
     * Tests that a single digit is rejected.
     *
     * A single digit is too short to be any valid card number.
     */
    public function testValidCcRejectsSingleDigit(): void
    {
        $this->assertFalse(valid_cc('4'));
    }

    /**
     * Tests that a 13-digit Visa card number is recognized as valid.
     *
     * Older Visa cards can have 13 digits.
     */
    public function testValidCcVisa13Digit(): void
    {
        $this->assertTrue(valid_cc('4222222222225'));
    }

    /**
     * Tests that a China UnionPay card is recognized.
     *
     * UnionPay cards start with 62 and have 16-19 digits.
     */
    public function testValidCcChinaUnionPay(): void
    {
        $this->assertTrue(valid_cc('6200000000000005'));
    }

    /**
     * Tests that a new 2-series MasterCard is recognized as valid.
     *
     * MasterCard added the 2221-2720 range in addition to 51-55.
     */
    public function testValidCcMasterCard2Series(): void
    {
        $this->assertTrue(valid_cc('2221000000000009'));
    }

    // ==================== Static analysis tests for DB-dependent functions ====================

    /**
     * Tests that cc.inc.php source file exists.
     *
     * This is the main billing functions file containing all credit card
     * related utilities.
     */
    public function testSourceFileExists(): void
    {
        $this->assertFileExists(self::$sourceFile);
    }

    /**
     * Tests that all expected functions are declared in cc.inc.php.
     *
     * Verifies the file contains declarations for all documented functions
     * used throughout the MyAdmin billing system.
     */
    public function testAllExpectedFunctionsDeclared(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $expectedFunctions = [
            'mask_cc',
            'valid_cc',
            'get_locked_ccs',
            'select_cc_exp',
            'can_use_cc',
            'format_cc_exp',
            'make_cc_decline',
            'email_cc_decline',
            'parse_ccs',
            'get_bad_cc',
            'charge_card',
            'auth_charge_card',
            'get_cc_bank_number',
            'get_cc_last_four',
        ];
        foreach ($expectedFunctions as $func) {
            $this->assertStringContainsString(
                "function {$func}(",
                $content,
                "Expected function '{$func}' not found in cc.inc.php"
            );
        }
    }

    /**
     * Tests that charge_card uses AUTH_CAPTURE transaction type.
     *
     * AUTH_CAPTURE simultaneously authorizes and captures the payment
     * in a single request to the Authorize.Net gateway.
     */
    public function testChargeCardUsesAuthCapture(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString("'x_Type' => 'AUTH_CAPTURE'", $content);
    }

    /**
     * Tests that auth_charge_card uses AUTH_ONLY transaction type.
     *
     * AUTH_ONLY authorizes the card without capturing, used for
     * verification charges that will not be settled.
     */
    public function testAuthChargeCardUsesAuthOnly(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString("'x_Type' => 'AUTH_ONLY'", $content);
    }

    /**
     * Tests that the valid_cc function covers all major card schemes.
     *
     * Ensures the validation patterns include all supported card types.
     */
    public function testValidCcSchemesIncludeAllMajorTypes(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $schemes = ['AMEX', 'CHINA_UNIONPAY', 'DINERS', 'DISCOVER', 'INSTAPAYMENT', 'JCB', 'LASER', 'MAESTRO', 'MASTERCARD', 'VISA'];
        foreach ($schemes as $scheme) {
            $this->assertStringContainsString("'{$scheme}'", $content, "Missing card scheme: {$scheme}");
        }
    }

    /**
     * Tests that the charge_card function communicates with the Authorize.Net gateway.
     *
     * The function must use the correct production endpoint URL.
     */
    public function testChargeCardUsesCorrectGatewayUrl(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString('https://secure.authorize.net/gateway/transact.dll', $content);
    }

    /**
     * Tests that charge_card strips credentials from log data.
     *
     * Sensitive API credentials must not appear in cc_log database entries.
     */
    public function testChargeCardStripsCredentials(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString("unset(\$rargs['x_Login'], \$rargs['x_Password']", $content);
    }
}
