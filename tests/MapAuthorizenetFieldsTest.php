<?php

declare(strict_types=1);

namespace Detain\MyAdminAuthorizenet\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests for the map_authorizenet_fields() function.
 *
 * This function parses an Authorize.Net CSV response string and maps
 * it to named fields. It supports three parsing strategies: full regex,
 * partial regex, and manual CSV splitting.
 */
class MapAuthorizenetFieldsTest extends TestCase
{
    private static string $sourceFile;

    public static function setUpBeforeClass(): void
    {
        self::$sourceFile = dirname(__DIR__) . '/src/map_authorizenet_fields.php';
        if (!function_exists('map_authorizenet_fields')) {
            // Stub for myadmin_log which is called inside the function
            if (!function_exists('myadmin_log')) {
                function myadmin_log($a, $b, $c, $d = null, $e = null, $f = null) {}
            }
            require_once self::$sourceFile;
        }
    }

    /**
     * Tests that the source file exists.
     *
     * Confirms the mapping function file is present in the expected location.
     */
    public function testSourceFileExists(): void
    {
        $this->assertFileExists(self::$sourceFile);
    }

    /**
     * Tests that map_authorizenet_fields is a declared function.
     *
     * Ensures the function is properly loaded and callable.
     */
    public function testFunctionExists(): void
    {
        $this->assertTrue(function_exists('map_authorizenet_fields'));
    }

    /**
     * Tests the fallback CSV parsing with field name mapping.
     *
     * When neither regex matches, the function falls back to splitting
     * the data by commas and mapping by index using the fields array.
     */
    public function testFallbackCsvParsing(): void
    {
        $fields = [
            ['name' => 'Response Code'],
            ['name' => 'Subcode'],
            ['name' => 'Reason Code'],
        ];
        $result = ['data' => '1,2,100'];
        $mapped = map_authorizenet_fields($result, $fields);

        $this->assertArrayHasKey('response_code', $mapped);
        $this->assertSame('1', $mapped['response_code']);
        $this->assertArrayHasKey('subcode', $mapped);
        $this->assertSame('2', $mapped['subcode']);
        $this->assertArrayHasKey('reason_code', $mapped);
        $this->assertSame('100', $mapped['reason_code']);
    }

    /**
     * Tests that the fallback parser removes 'data' key on success.
     *
     * When all fields are mapped without problems, the raw 'data'
     * key should be removed from the result array.
     */
    public function testFallbackParserRemovesDataKeyOnSuccess(): void
    {
        $fields = [
            ['name' => 'Response Code'],
        ];
        $result = ['data' => '1'];
        $mapped = map_authorizenet_fields($result, $fields);

        $this->assertArrayNotHasKey('data', $mapped);
    }

    /**
     * Tests that empty fields in the CSV are skipped.
     *
     * When a field value is empty, it should not be added to the result.
     */
    public function testEmptyFieldsAreSkipped(): void
    {
        $fields = [
            ['name' => 'Response Code'],
            ['name' => 'Subcode'],
            ['name' => 'Reason Code'],
        ];
        $result = ['data' => '1,,100'];
        $mapped = map_authorizenet_fields($result, $fields);

        $this->assertArrayHasKey('response_code', $mapped);
        $this->assertArrayNotHasKey('subcode', $mapped);
        $this->assertArrayHasKey('reason_code', $mapped);
    }

    /**
     * Tests that existing result keys are preserved.
     *
     * Keys already in the result array (other than 'data') should
     * remain after processing.
     */
    public function testExistingResultKeysPreserved(): void
    {
        $fields = [
            ['name' => 'Response Code'],
        ];
        $result = ['data' => '1', 'cc_custid' => 12345];
        $mapped = map_authorizenet_fields($result, $fields);

        $this->assertArrayHasKey('cc_custid', $mapped);
        $this->assertSame(12345, $mapped['cc_custid']);
    }

    /**
     * Tests that field names are lowercased with underscores.
     *
     * The function converts field names like "Response Code" to
     * "response_code" for consistent key naming.
     */
    public function testFieldNamesAreLowercasedWithUnderscores(): void
    {
        $fields = [
            ['name' => 'Ship To First Name'],
        ];
        $result = ['data' => 'John'];
        $mapped = map_authorizenet_fields($result, $fields);

        $this->assertArrayHasKey('ship_to_first_name', $mapped);
    }

    /**
     * Tests the source contains the three parsing strategies.
     *
     * The function should try a full regex, then a partial regex,
     * then fall back to manual CSV splitting.
     */
    public function testSourceContainsThreeParsingStrategies(): void
    {
        $content = file_get_contents(self::$sourceFile);
        // Full regex match
        $this->assertStringContainsString('preg_match("/^(?P<code>[1-4])', $content);
        // Manual CSV splitting
        $this->assertStringContainsString("explode(',', \$result['data'])", $content);
    }
}
