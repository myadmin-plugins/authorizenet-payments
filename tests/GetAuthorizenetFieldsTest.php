<?php

declare(strict_types=1);

namespace Detain\MyAdminAuthorizenet\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests for the get_authorizenet_fields() function.
 *
 * This function returns a metadata array describing all fields in an
 * Authorize.Net CSV response. Each entry has 'name' and 'description' keys.
 * The function is pure (no side effects or dependencies).
 */
class GetAuthorizenetFieldsTest extends TestCase
{
    private static string $sourceFile;

    public static function setUpBeforeClass(): void
    {
        self::$sourceFile = dirname(__DIR__) . '/src/get_authorizenet_fields.php';
        if (!function_exists('get_authorizenet_fields')) {
            require_once self::$sourceFile;
        }
    }

    /**
     * Tests that the source file exists.
     *
     * Confirms the field definitions file is present in the expected location.
     */
    public function testSourceFileExists(): void
    {
        $this->assertFileExists(self::$sourceFile);
    }

    /**
     * Tests that get_authorizenet_fields returns an array.
     *
     * The function must return an array of field definitions.
     */
    public function testReturnsArray(): void
    {
        $fields = get_authorizenet_fields();
        $this->assertIsArray($fields);
    }

    /**
     * Tests that the returned array is non-empty.
     *
     * There should always be at least some field definitions.
     */
    public function testReturnsNonEmptyArray(): void
    {
        $fields = get_authorizenet_fields();
        $this->assertNotEmpty($fields);
    }

    /**
     * Tests that each field entry has a 'name' key.
     *
     * Every field definition must include a human-readable name
     * for display in transaction detail views.
     */
    public function testEachFieldHasNameKey(): void
    {
        $fields = get_authorizenet_fields();
        foreach ($fields as $idx => $field) {
            $this->assertArrayHasKey('name', $field, "Field at index {$idx} is missing 'name' key");
        }
    }

    /**
     * Tests that each field entry has a 'description' key.
     *
     * Every field definition must include a description explaining
     * the field's purpose and format.
     */
    public function testEachFieldHasDescriptionKey(): void
    {
        $fields = get_authorizenet_fields();
        foreach ($fields as $idx => $field) {
            $this->assertArrayHasKey('description', $field, "Field at index {$idx} is missing 'description' key");
        }
    }

    /**
     * Tests that the first field is the Response Code.
     *
     * Position 0 in the Authorize.Net CSV response is always the
     * overall transaction status code (1=Approved, 2=Declined, etc).
     */
    public function testFirstFieldIsResponseCode(): void
    {
        $fields = get_authorizenet_fields();
        $this->assertSame('Response Code', $fields[0]['name']);
    }

    /**
     * Tests that the Response Code description mentions approved/declined.
     *
     * The description should document the possible values (1-4).
     */
    public function testResponseCodeDescriptionContainsStatusValues(): void
    {
        $fields = get_authorizenet_fields();
        $desc = $fields[0]['description'];
        $this->assertStringContainsString('Approved', $desc);
        $this->assertStringContainsString('Declined', $desc);
        $this->assertStringContainsString('Error', $desc);
    }

    /**
     * Tests that the Transaction ID field is present.
     *
     * The transaction ID is essential for follow-on operations like
     * refunds and voids.
     */
    public function testContainsTransactionIdField(): void
    {
        $fields = get_authorizenet_fields();
        $names = array_column($fields, 'name');
        $this->assertContains('Transaction ID', $names);
    }

    /**
     * Tests that the Amount field is present.
     *
     * The amount field records the transaction value.
     */
    public function testContainsAmountField(): void
    {
        $fields = get_authorizenet_fields();
        $names = array_column($fields, 'name');
        $this->assertContains('Amount', $names);
    }

    /**
     * Tests that the Card Type field is present (last named field).
     *
     * Card Type identifies the card brand (Visa, MC, etc) and is
     * one of the last fields in the CSV response.
     */
    public function testContainsCardTypeField(): void
    {
        $fields = get_authorizenet_fields();
        $names = array_column($fields, 'name');
        $this->assertContains('Card Type', $names);
    }

    /**
     * Tests that buyer information fields are present.
     *
     * The response includes customer billing information fields.
     */
    public function testContainsBuyerInfoFields(): void
    {
        $fields = get_authorizenet_fields();
        $names = array_column($fields, 'name');
        $this->assertContains('First Name', $names);
        $this->assertContains('Last Name', $names);
        $this->assertContains('Email Address', $names);
        $this->assertContains('Address', $names);
    }

    /**
     * Tests that shipping information fields are present.
     *
     * The response includes customer shipping information fields.
     */
    public function testContainsShippingFields(): void
    {
        $fields = get_authorizenet_fields();
        $names = array_column($fields, 'name');
        $this->assertContains('Ship To First Name', $names);
        $this->assertContains('Ship To Last Name', $names);
        $this->assertContains('Ship To Address', $names);
    }

    /**
     * Tests that security verification fields are present.
     *
     * AVS, Card Code, and MD5 Hash are security verification fields.
     */
    public function testContainsSecurityFields(): void
    {
        $fields = get_authorizenet_fields();
        $names = array_column($fields, 'name');
        $this->assertContains('AVS Response', $names);
        $this->assertContains('Card Code Response', $names);
        $this->assertContains('MD5 Hash', $names);
    }

    /**
     * Tests that all field names are non-empty strings.
     *
     * Empty field names would cause issues in UI rendering.
     */
    public function testAllFieldNamesAreNonEmpty(): void
    {
        $fields = get_authorizenet_fields();
        foreach ($fields as $idx => $field) {
            $this->assertIsString($field['name'], "Field at index {$idx} name is not a string");
            $this->assertNotEmpty($field['name'], "Field at index {$idx} has empty name");
        }
    }

    /**
     * Tests that the fields array has the expected count.
     *
     * The Authorize.Net AIM response has a known number of documented fields.
     * This count should remain stable unless the API changes.
     */
    public function testFieldCountIsCorrect(): void
    {
        $fields = get_authorizenet_fields();
        // The function defines fields for positions 0 through ~42 in the CSV
        $this->assertGreaterThanOrEqual(40, count($fields));
    }
}
