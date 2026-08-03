<?php

declare(strict_types=1);

namespace Detain\MyAdminAuthorizenet\Tests;

use Detain\MyAdminAuthorizenet\Plugin;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\GenericEvent;

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
        // Stubs.php supplies the global helpers and \MyAdmin\App statics cc.inc.php calls.
        require_once __DIR__ . '/Stubs.php';
        if (!function_exists('mask_cc')) {
            require_once self::$sourceFile;
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        FrameworkState::reset();
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
     * Tests that every function this package advertises cc.inc.php as the provider of
     * is really callable once cc.inc.php is loaded.
     *
     * This replaces testAllExpectedFunctionsDeclared(), which grepped the source for
     * "function <name>(" against a list hardcoded in the test. That list was a second,
     * unenforced copy of the truth: it went stale the moment a function was removed, and
     * updating it to match the source would have been the only way to make it pass —
     * which is exactly the change that hides the problem.
     *
     * The expected names are now derived from Plugin::getRequirements(), which is what
     * actually matters at runtime: function_requirements('x') looks x up in that map,
     * includes the registered file and expects x to exist afterwards. A name registered
     * against cc.inc.php that the file does not define is a broken lazy-load — and for
     * anything registered with add_page_requirement() it is also a routable URL with no
     * function behind it.
     */
    public function testEveryFunctionRegisteredAgainstCcIncPhpIsCallable(): void
    {
        $loader = new class {
            /** @var list<array{kind: string, name: string, path: string}> */
            public array $requirements = [];

            public function add_requirement(string $name, string $path, $methods = false): void
            {
                $this->requirements[] = ['kind' => 'function', 'name' => $name, 'path' => $path];
            }

            public function add_page_requirement(string $name, string $path, $methods = false): void
            {
                $this->requirements[] = ['kind' => 'page', 'name' => $name, 'path' => $path];
            }
        };

        Plugin::getRequirements(new GenericEvent($loader));

        $fromCcIncPhp = array_values(array_filter(
            $loader->requirements,
            static fn (array $requirement): bool => substr($requirement['path'], -strlen('/cc.inc.php')) === '/cc.inc.php'
        ));

        $this->assertNotEmpty(
            $fromCcIncPhp,
            'the plugin should register cc.inc.php as the source of the credit card helpers'
        );

        foreach ($fromCcIncPhp as $requirement) {
            $this->assertTrue(
                function_exists($requirement['name']),
                sprintf(
                    "Plugin::getRequirements() registers %s '%s' as provided by cc.inc.php, but the file does not define it. "
                        . 'function_requirements(\'%s\') would load the file and still leave the call undefined%s.',
                    $requirement['kind'],
                    $requirement['name'],
                    $requirement['name'],
                    $requirement['kind'] === 'page'
                        ? ", and /{$requirement['name']} plus /admin/{$requirement['name']} would be routed at nothing"
                        : ''
                )
            );
            $this->assertIsCallable($requirement['name']);
        }
    }

    /**
     * Tests that format_cc_exp() builds a zero-padded MM/YYYY expiry from the request.
     *
     * Authorize.Net rejects a single-digit month, so the padding is the whole point of
     * the function.
     */
    public function testFormatCcExpZeroPadsSingleDigitMonth(): void
    {
        FrameworkState::$request = ['exp_month' => '7', 'exp_year' => '2030'];

        $this->assertSame('07/2030', format_cc_exp());
    }

    /**
     * Tests that a two-digit month is passed through unpadded.
     */
    public function testFormatCcExpLeavesTwoDigitMonthAlone(): void
    {
        FrameworkState::$request = ['exp_month' => '11', 'exp_year' => '2031'];

        $this->assertSame('11/2031', format_cc_exp());
    }

    /**
     * Tests that a missing expiry falls back to January of the current year.
     */
    public function testFormatCcExpFallsBackWhenRequestIsEmpty(): void
    {
        FrameworkState::$request = [];

        $this->assertSame('01/' . date('Y'), format_cc_exp());
    }

    /**
     * Tests that get_cc_bank_number() returns the six digit BIN of the decrypted card.
     */
    public function testGetCcBankNumberReturnsBinOfDecryptedCard(): void
    {
        $this->assertSame('411111', get_cc_bank_number(\MyAdmin\App::encrypt('4111111111111111')));
    }

    /**
     * Tests that get_cc_last_four() returns the last four digits of the decrypted card.
     */
    public function testGetCcLastFourReturnsLastFourOfDecryptedCard(): void
    {
        $this->assertSame('1881', get_cc_last_four(\MyAdmin\App::encrypt('4111111111111881')));
    }

    /**
     * Tests that parse_ccs() adds the account's primary card to the card list.
     */
    public function testParseCcsAddsPrimaryCardToTheList(): void
    {
        $ccs = parse_ccs([
            'ccs' => json_encode([
                ['cc' => \MyAdmin\App::encrypt('5555555555554444'), 'cc_exp' => '05/2029'],
            ]),
            'cc' => \MyAdmin\App::encrypt('4111111111111111'),
            'cc_exp' => '01/2030',
        ]);

        $this->assertCount(2, $ccs, 'the primary card should be appended to the stored card list');
        $this->assertSame('01/2030', $ccs[1]['cc_exp'], "the primary card's expiry should be carried over");
    }

    /**
     * Tests that parse_ccs() does not list the primary card twice when it is already
     * one of the stored cards. Duplicates would make the retry logic try the same
     * declined card again.
     */
    public function testParseCcsDoesNotDuplicateAPrimaryCardAlreadyStored(): void
    {
        $ccs = parse_ccs([
            'ccs' => json_encode([
                ['cc' => \MyAdmin\App::encrypt('4111111111111111'), 'cc_exp' => '01/2030'],
                ['cc' => \MyAdmin\App::encrypt('5555555555554444'), 'cc_exp' => '05/2029'],
            ]),
            'cc' => \MyAdmin\App::encrypt('4111111111111111'),
            'cc_exp' => '01/2030',
        ]);

        $this->assertCount(2, $ccs, 'a primary card that is already stored must not be added again');
    }

    /**
     * Tests that formatting differences in a stored card do not defeat the duplicate
     * check: spaces, dashes and underscores are normalised away before comparing.
     */
    public function testParseCcsNormalisesSeparatorsWhenDetectingDuplicates(): void
    {
        $ccs = parse_ccs([
            'ccs' => json_encode([
                ['cc' => \MyAdmin\App::encrypt('4111-1111 1111_1111'), 'cc_exp' => '01/2030'],
            ]),
            'cc' => \MyAdmin\App::encrypt('4111111111111111'),
            'cc_exp' => '01/2030',
        ]);

        $this->assertCount(1, $ccs, 'the same card written with separators is still the same card');
    }

    /**
     * Tests that an account with no cards at all yields an empty list rather than an
     * entry for a blank card number.
     */
    public function testParseCcsReturnsEmptyListForAccountWithNoCards(): void
    {
        $this->assertSame([], parse_ccs([]));
        $this->assertSame([], parse_ccs(['cc' => '']));
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
