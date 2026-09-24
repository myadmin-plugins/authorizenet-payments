<?php

declare(strict_types=1);

namespace Detain\MyAdminAuthorizenet\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests for functions in verify_cc.php via static analysis.
 *
 * The verify_cc and verify_cc_charge functions implement a two-charge
 * verification process for new credit cards. They depend on global state
 * and external services, so we test via source analysis.
 */
class VerifyCcTest extends TestCase
{
    private static string $sourceFile;

    public static function setUpBeforeClass(): void
    {
        self::$sourceFile = dirname(__DIR__) . '/src/verify_cc.php';
    }

    /**
     * Tests that the verify_cc function is declared.
     *
     * This function handles the amount-matching step of CC verification.
     */
    public function testVerifyCcFunctionDeclared(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString('function verify_cc(', $content);
    }

    /**
     * Tests that the verify_cc_charge function is declared.
     *
     * This function handles the initial charge step of CC verification.
     */
    public function testVerifyCcChargeFunctionDeclared(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString('function verify_cc_charge(', $content);
    }

    /**
     * Tests that verify_cc_charge validates CC presence.
     *
     * The function should return an error if no CC is set in the input array.
     */
    public function testVerifyCcChargeValidatesCcPresence(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString("'No CC set/present'", $content);
    }

    /**
     * Tests that verify_cc_charge generates random amounts under $1.
     *
     * The verification amounts are random values between $0.01 and $0.99.
     */
    public function testVerifyCcChargeUsesRandomAmounts(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString('mt_rand(1, 99) / 100', $content);
    }

    /**
     * Tests that verify_cc_charge calls auth_charge_card for validation.
     *
     * The function uses AUTH_ONLY charges to verify the card.
     */
    public function testVerifyCcChargeCallsAuthChargeCard(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString('auth_charge_card(', $content);
    }

    /**
     * Tests that verify_cc returns a structured result array.
     *
     * Both functions return arrays with 'status' and 'text' keys.
     */
    public function testVerifyCcReturnsStructuredResult(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString("'status' => ''", $content);
        $this->assertStringContainsString("'text' => ''", $content);
    }

    /**
     * Tests that verify_cc supports status values: ok, failed, error.
     *
     * These statuses indicate the verification outcome.
     */
    public function testVerifyCcSupportsExpectedStatuses(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString("'status'] = 'ok'", $content);
        $this->assertStringContainsString("'status'] = 'failed'", $content);
        $this->assertStringContainsString("'status'] = 'error'", $content);
    }

    /**
     * Tests that verify_cc allows amounts to match within tolerance.
     *
     * The verification uses a tolerance of 0.06 for amount matching,
     * allowing for minor rounding differences.
     */
    public function testVerifyCcUsesAmountTolerance(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString('0.06', $content);
    }

    /**
     * Tests that verify_cc supports reversed amount entry.
     *
     * Users may enter the two amounts in either order; the function
     * checks both orderings.
     */
    public function testVerifyCcSupportsReversedAmounts(): void
    {
        $content = file_get_contents(self::$sourceFile);
        // The amounts now come from CcMeta rather than $data['cc_amt1_<PAN>'], but the
        // behaviour these assertions protect is unchanged: both orderings are compared,
        // because a customer may enter the two amounts either way round.
        $this->assertStringContainsString("'amt1'", $content);
        $this->assertStringContainsString("'amt2'", $content);
        $this->assertStringContainsString('$ourAmt1', $content);
        $this->assertStringContainsString('$ourAmt2', $content);
    }

    /**
     * Tests that verify_cc tracks failed attempts.
     *
     * Failed verification attempts are counted to limit abuse.
     */
    public function testVerifyCcTracksFailedAttempts(): void
    {
        $content = file_get_contents(self::$sourceFile);
        // Counted through CcMeta::increment() rather than a flat cc_fails_<PAN> row.
        // The increment is the point: the old `1 + $data['cc_fails_'.$pan]` computed the
        // new value from a request-start snapshot, so parallel wrong-amount submissions
        // all read the same value and the card never reached the lock.
        $this->assertStringContainsString("CcMeta::increment", $content);
        $this->assertStringContainsString("'fails'", $content);
        // And the flat-key write is gone: the PAN is no longer concatenated into an
        // accounts_ext key here. (Asserting on the old `1 + $data[...]` form instead
        // would be self-defeating -- the comment explaining it contains that literal.)
        $this->assertStringNotContainsString('cc_fails_', $content);
    }

    /**
     * Tests that verify_cc enables the card on successful verification.
     *
     * On match, the function sets cc_auth, payment_method=cc, and
     * disable_cc=0.
     */
    public function testVerifyCcEnablesCardOnSuccess(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString("'payment_method' => 'cc'", $content);
        $this->assertStringContainsString("'disable_cc' => 0", $content);
    }

    /**
     * Tests that verify_cc validates missing/blank amounts.
     *
     * The function checks for both missing and blank amount values.
     */
    public function testVerifyCcValidatesMissingAmounts(): void
    {
        $content = file_get_contents(self::$sourceFile);
        $this->assertStringContainsString('Missing or Blank Amount Passed', $content);
    }
}
