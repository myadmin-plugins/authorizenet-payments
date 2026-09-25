<?php

declare(strict_types=1);

namespace Detain\MyAdminAuthorizenet\Tests;

use PHPUnit\Framework\TestCase;

/**
 * cc_log must never hold the card security code, and holds the card number
 * only as mask_cc() output; the log lines around charging must not carry the
 * full card number, the CVV or the gateway password.
 */
class CcLogRedactionTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/Stubs.php';
        if (!function_exists('cc_log_redact_request')) {
            require_once dirname(__DIR__) . '/src/cc.inc.php';
        }
    }

    public function testCvvIsBlankedAndThePanIsMasked(): void
    {
        $row = \cc_log_redact_request([
            'cc_custid' => 5,
            'cc_request_card_num' => '4111111111111111',
            'cc_request_card_code' => '123',
            'cc_request_exp_date' => '12/2030',
            'cc_request_amount' => 9.99,
        ]);
        $this->assertSame('', $row['cc_request_card_code']);
        $this->assertSame('************1111', $row['cc_request_card_num']);
        $this->assertSame('12/2030', $row['cc_request_exp_date']);
        $this->assertSame(9.99, $row['cc_request_amount']);
    }

    public function testMaskedPanMatchesWhatTheCoreCouponCheckBuilds(): void
    {
        // core coupons.inc.php strips ' ', '_' and '-' from the decrypted PAN, then calls mask_cc()
        $pan = '4111 1111-1111_1111';
        $coreForm = \mask_cc(str_replace([' ', '_', '-'], ['', '', ''], trim($pan)));
        $row = \cc_log_redact_request(['cc_request_card_num' => $pan]);
        $this->assertSame($coreForm, $row['cc_request_card_num']);
        $this->assertSame('************1111', $row['cc_request_card_num']);
    }

    public function testLastFourOnlyValuesFromRefundAndVoidAreUnchanged(): void
    {
        $row = \cc_log_redact_request(['cc_request_card_num' => '1111', 'cc_result_trans_id' => '60012345678']);
        $this->assertSame('1111', $row['cc_request_card_num']);
        $this->assertSame('', $row['cc_request_card_code']);
        $this->assertSame('60012345678', $row['cc_result_trans_id']);
    }

    public function testRowWithoutACardNumberGetsNoCardNumber(): void
    {
        $row = \cc_log_redact_request(['cc_custid' => 1]);
        $this->assertArrayNotHasKey('cc_request_card_num', $row);
        $this->assertSame('', $row['cc_request_card_code']);
    }

    public function testEveryCcLogInsertIsRedactedFirst(): void
    {
        foreach (['/src/cc.inc.php' => 2, '/src/AuthorizeNetCC.php' => 2] as $file => $expected) {
            $source = (string) file_get_contents(dirname(__DIR__) . $file);
            $this->assertSame($expected, substr_count($source, "make_insert_query('cc_log', \$cc_log)"), $file);
            $this->assertSame($expected, substr_count($source, '$cc_log = cc_log_redact_request($cc_log);'), $file);
            // each redaction happens before its insert
            $offset = 0;
            for ($i = 0; $i < $expected; $i++) {
                $redact = strpos($source, '$cc_log = cc_log_redact_request($cc_log);', $offset);
                $insert = strpos($source, "make_insert_query('cc_log', \$cc_log)", $offset);
                $this->assertNotFalse($redact);
                $this->assertLessThan($insert, $redact, $file);
                $offset = $insert + 1;
            }
        }
    }

    public function testNoLogLinePrintsTheFullCardOrTheRequestArgs(): void
    {
        foreach (['cc.inc.php', 'manage_cc.php', 'verify_cc.php', 'add_cc.php', 'AuthorizeNetCC.php'] as $file) {
            $source = (string) file_get_contents(dirname(__DIR__) . '/src/' . $file);
            preg_match_all('/^\s*myadmin_log\(.*$/m', $source, $lines);
            foreach ($lines[0] as $line) {
                $this->assertStringNotContainsString('json_encode($args', $line, $file);
                $this->assertStringNotContainsString('{$cc_decrypted}', $line, $file);
                $this->assertDoesNotMatchRegularExpression("/'Checking CC '\\.\\\\?MyAdmin\\\\App::decrypt/", $line, $file);
                $this->assertDoesNotMatchRegularExpression("/invalid card format for:'\\.trim/", $line, $file);
            }
        }
        $cc = (string) file_get_contents(dirname(__DIR__) . '/src/cc.inc.php');
        $this->assertStringNotContainsString('App::decrypt($cc_holder[$cc_field])." is not verified.', $cc);
    }
}
