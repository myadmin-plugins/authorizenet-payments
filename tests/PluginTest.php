<?php

declare(strict_types=1);

namespace Detain\MyAdminAuthorizenet\Tests;

use PHPUnit\Framework\TestCase;
use Detain\MyAdminAuthorizenet\Plugin;
use ReflectionClass;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Tests for the Plugin class.
 *
 * Covers class structure, static properties, hook registration,
 * and method signatures using reflection-based analysis.
 */
class PluginTest extends TestCase
{
    /**
     * Tests that the Plugin class exists and can be loaded.
     *
     * Verifies the autoloader correctly resolves the Plugin class
     * from the Detain\MyAdminAuthorizenet namespace.
     */
    public function testPluginClassExists(): void
    {
        $this->assertTrue(class_exists(Plugin::class));
    }

    /**
     * Tests that the Plugin class can be instantiated.
     *
     * The constructor is empty, so instantiation should always succeed
     * without any dependencies.
     */
    public function testPluginCanBeInstantiated(): void
    {
        $plugin = new Plugin();
        $this->assertInstanceOf(Plugin::class, $plugin);
    }

    /**
     * Tests that the $name static property is set correctly.
     *
     * The plugin name is used for identification within the MyAdmin
     * plugin system and must be a non-empty string.
     */
    public function testNameStaticProperty(): void
    {
        $this->assertSame('Authorizenet Plugin', Plugin::$name);
    }

    /**
     * Tests that the $description static property is set correctly.
     *
     * The description provides human-readable information about the
     * plugin's purpose for display in admin interfaces.
     */
    public function testDescriptionStaticProperty(): void
    {
        $this->assertSame(
            'Allows handling of Authorizenet based Payments through their Payment Processor/Payment System.',
            Plugin::$description
        );
    }

    /**
     * Tests that the $help static property is an empty string.
     *
     * The help property is reserved for future use and should be
     * initialized as an empty string.
     */
    public function testHelpStaticProperty(): void
    {
        $this->assertSame('', Plugin::$help);
    }

    /**
     * Tests that the $type static property is set to 'plugin'.
     *
     * This type identifier is used by the MyAdmin plugin loader to
     * categorize this package.
     */
    public function testTypeStaticProperty(): void
    {
        $this->assertSame('plugin', Plugin::$type);
    }

    /**
     * Tests that getHooks returns the correct event hook mappings.
     *
     * The hooks array maps Symfony event names to static method callbacks
     * on the Plugin class. This is how the plugin registers itself with
     * the MyAdmin event dispatcher.
     */
    public function testGetHooksReturnsCorrectArray(): void
    {
        $hooks = Plugin::getHooks();

        $this->assertIsArray($hooks);
        $this->assertArrayHasKey('system.settings', $hooks);
        $this->assertArrayHasKey('function.requirements', $hooks);
        $this->assertSame([Plugin::class, 'getSettings'], $hooks['system.settings']);
        $this->assertSame([Plugin::class, 'getRequirements'], $hooks['function.requirements']);
    }

    /**
     * Tests that getHooks does not include the commented-out ui.menu hook.
     *
     * The ui.menu hook is commented out in the source, so it should not
     * appear in the returned hooks array.
     */
    public function testGetHooksDoesNotIncludeMenuHook(): void
    {
        $hooks = Plugin::getHooks();
        $this->assertArrayNotHasKey('ui.menu', $hooks);
    }

    /**
     * Tests that getHooks returns exactly 2 hooks.
     *
     * Ensures no extra hooks are accidentally registered.
     */
    public function testGetHooksReturnsExactlyTwoHooks(): void
    {
        $hooks = Plugin::getHooks();
        $this->assertCount(2, $hooks);
    }

    /**
     * Tests that the getMenu static method exists and accepts a GenericEvent.
     *
     * Uses reflection to verify the method signature without invoking it,
     * since it depends on global state ($GLOBALS['tf']).
     */
    public function testGetMenuMethodSignature(): void
    {
        $reflection = new ReflectionClass(Plugin::class);
        $method = $reflection->getMethod('getMenu');

        $this->assertTrue($method->isStatic());
        $this->assertTrue($method->isPublic());
        $this->assertCount(1, $method->getParameters());

        $param = $method->getParameters()[0];
        $this->assertSame('event', $param->getName());
        $this->assertSame(GenericEvent::class, $param->getType()->getName());
    }

    /**
     * Tests that the getRequirements static method exists and accepts a GenericEvent.
     *
     * Uses reflection to verify the method signature. This method registers
     * all the file requirements for the plugin's functions and pages.
     */
    public function testGetRequirementsMethodSignature(): void
    {
        $reflection = new ReflectionClass(Plugin::class);
        $method = $reflection->getMethod('getRequirements');

        $this->assertTrue($method->isStatic());
        $this->assertTrue($method->isPublic());
        $this->assertCount(1, $method->getParameters());

        $param = $method->getParameters()[0];
        $this->assertSame('event', $param->getName());
        $this->assertSame(GenericEvent::class, $param->getType()->getName());
    }

    /**
     * Tests that the getSettings static method exists and accepts a GenericEvent.
     *
     * Uses reflection to verify the method signature. This method registers
     * Authorize.Net-specific configuration settings.
     */
    public function testGetSettingsMethodSignature(): void
    {
        $reflection = new ReflectionClass(Plugin::class);
        $method = $reflection->getMethod('getSettings');

        $this->assertTrue($method->isStatic());
        $this->assertTrue($method->isPublic());
        $this->assertCount(1, $method->getParameters());

        $param = $method->getParameters()[0];
        $this->assertSame('event', $param->getName());
        $this->assertSame(GenericEvent::class, $param->getType()->getName());
    }

    /**
     * Tests the Plugin class has exactly 4 static properties.
     *
     * Ensures no unintended properties are added to the class.
     */
    public function testPluginHasExpectedStaticProperties(): void
    {
        $reflection = new ReflectionClass(Plugin::class);
        $staticProperties = $reflection->getStaticProperties();

        $this->assertArrayHasKey('name', $staticProperties);
        $this->assertArrayHasKey('description', $staticProperties);
        $this->assertArrayHasKey('help', $staticProperties);
        $this->assertArrayHasKey('type', $staticProperties);
        $this->assertCount(4, $staticProperties);
    }

    /**
     * Tests that the Plugin class is in the correct namespace.
     *
     * Verifies the class is registered under Detain\MyAdminAuthorizenet
     * as defined in the composer.json autoload configuration.
     */
    public function testPluginNamespace(): void
    {
        $reflection = new ReflectionClass(Plugin::class);
        $this->assertSame('Detain\MyAdminAuthorizenet', $reflection->getNamespaceName());
    }

    /**
     * Tests that Plugin has exactly 4 public methods (constructor + 3 static).
     *
     * Validates the class API surface to ensure no methods are accidentally
     * added or removed.
     */
    public function testPluginMethodCount(): void
    {
        $reflection = new ReflectionClass(Plugin::class);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        $methodNames = array_map(fn($m) => $m->getName(), $methods);
        $this->assertContains('__construct', $methodNames);
        $this->assertContains('getHooks', $methodNames);
        $this->assertContains('getMenu', $methodNames);
        $this->assertContains('getRequirements', $methodNames);
        $this->assertContains('getSettings', $methodNames);
        $this->assertCount(5, $methods);
    }

    /**
     * Tests that the constructor takes no parameters.
     *
     * The Plugin constructor is empty and should not require any arguments.
     */
    public function testConstructorHasNoParameters(): void
    {
        $reflection = new ReflectionClass(Plugin::class);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertCount(0, $constructor->getParameters());
    }

    /**
     * Tests that all hook callbacks reference callable static methods.
     *
     * Ensures the hooks array only references methods that actually exist
     * on the Plugin class, preventing runtime errors when events fire.
     */
    public function testAllHookCallbacksAreCallable(): void
    {
        $hooks = Plugin::getHooks();
        foreach ($hooks as $eventName => $callback) {
            $this->assertIsArray($callback, "Hook '{$eventName}' should be an array callback");
            $this->assertCount(2, $callback, "Hook '{$eventName}' should have [class, method]");
            $this->assertTrue(
                method_exists($callback[0], $callback[1]),
                "Method {$callback[0]}::{$callback[1]} referenced in hook '{$eventName}' does not exist"
            );
        }
    }
}
