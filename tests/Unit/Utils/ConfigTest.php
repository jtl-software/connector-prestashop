<?php

declare(strict_types=1);

namespace Tests\Unit\Utils;

use jtl\Connector\Presta\Utils\Config;
use PHPUnit\Framework\TestCase;

/**
 * Subclass that exposes cloning so the protected __clone() body is exercised.
 */
final class CloneableConfig extends Config
{
    public static function cloneInstance(string $file): Config
    {
        $instance = static::getInstance($file);
        return clone $instance;
    }
}

final class ConfigTest extends TestCase
{
    private string $configDir;
    private string $configFile;

    protected function setUp(): void
    {
        $this->configDir  = \CONNECTOR_DIR . '/config';
        $this->configFile = $this->configDir . '/config.json';

        // Ensure the config directory exists
        if (!\is_dir($this->configDir)) {
            \mkdir($this->configDir, 0777, true);
        }

        // Remove any leftover config file so each test starts clean
        if (\file_exists($this->configFile)) {
            \unlink($this->configFile);
        }

        $this->resetConfig();
    }

    protected function tearDown(): void
    {
        if (\file_exists($this->configFile)) {
            \unlink($this->configFile);
        }

        $this->resetConfig();
    }

    // -------------------------------------------------------------------------
    // Helper: reset singleton and static data via Reflection
    // -------------------------------------------------------------------------

    private function resetConfig(): void
    {
        $ref = new \ReflectionClass(Config::class);
        foreach (['instance', 'data'] as $prop) {
            $p = $ref->getProperty($prop);
            $p->setAccessible(true);
            $p->setValue(null, null);
        }
    }

    // -------------------------------------------------------------------------
    // getInstance()
    // -------------------------------------------------------------------------

    public function testGetInstanceReturnsConfigObject(): void
    {
        self::assertInstanceOf(Config::class, Config::getInstance($this->configFile));
    }

    public function testGetInstanceReturnsSameInstanceOnRepeatedCalls(): void
    {
        $first  = Config::getInstance($this->configFile);
        $second = Config::getInstance($this->configFile);
        self::assertSame($first, $second);
    }

    public function testGetInstanceWithNonExistentFileReturnsConfigAndDataIsStdClass(): void
    {
        $instance = Config::getInstance('/nonexistent/path/config.json');
        self::assertInstanceOf(Config::class, $instance);
        self::assertInstanceOf(\stdClass::class, Config::getData());
    }

    public function testGetInstanceWithValidJsonFilePopulatesData(): void
    {
        \file_put_contents($this->configFile, \json_encode(['token' => 'abc123', 'version' => 2]));

        Config::getInstance($this->configFile);

        self::assertSame('abc123', Config::get('token'));
        self::assertSame(2, Config::get('version'));
    }

    public function testGetInstanceWithInvalidJsonFallsBackToEmptyStdClass(): void
    {
        \file_put_contents($this->configFile, '{ not valid json }}}');

        Config::getInstance($this->configFile);

        self::assertInstanceOf(\stdClass::class, Config::getData());
        self::assertNull(Config::get('anything'));
    }

    public function testGetInstanceWithEmptyFileContentFallsBackToEmptyStdClass(): void
    {
        // json_decode('') returns null  → should become stdClass
        \file_put_contents($this->configFile, '');

        Config::getInstance($this->configFile);

        self::assertInstanceOf(\stdClass::class, Config::getData());
    }

    // -------------------------------------------------------------------------
    // getData()
    // -------------------------------------------------------------------------

    public function testGetDataReturnsStdClassAfterInitialisationWithoutFile(): void
    {
        Config::getInstance($this->configFile); // file does not exist → stdClass
        self::assertInstanceOf(\stdClass::class, Config::getData());
    }

    // -------------------------------------------------------------------------
    // get() / has()
    // -------------------------------------------------------------------------

    public function testGetNonexistentKeyReturnsNull(): void
    {
        Config::getInstance($this->configFile);
        self::assertNull(Config::get('nonexistent'));
    }

    public function testHasNonexistentKeyReturnsFalse(): void
    {
        Config::getInstance($this->configFile);
        self::assertFalse(Config::has('nonexistent'));
    }

    // -------------------------------------------------------------------------
    // set()
    // -------------------------------------------------------------------------

    public function testSetAndGetStringValue(): void
    {
        Config::getInstance($this->configFile);
        Config::set('key', 'value');
        self::assertSame('value', Config::get('key'));
    }

    public function testSetAndHasKeyReturnsTrue(): void
    {
        Config::getInstance($this->configFile);
        Config::set('key', 'value');
        self::assertTrue(Config::has('key'));
    }

    public function testSetIntegerValueAndGetReturnsInteger(): void
    {
        Config::getInstance($this->configFile);
        Config::set('count', 42);
        self::assertSame(42, Config::get('count'));
    }

    public function testSetPersistsValueToJsonFile(): void
    {
        Config::getInstance($this->configFile);
        Config::set('persistent', 'yes');

        // Reset singleton so next getInstance() re-reads the file
        $this->resetConfig();
        Config::getInstance($this->configFile);

        self::assertSame('yes', Config::get('persistent'));
    }

    public function testSetOverwritesExistingKeyValue(): void
    {
        Config::getInstance($this->configFile);
        Config::set('key', 'first');
        Config::set('key', 'second');
        self::assertSame('second', Config::get('key'));
    }

    // -------------------------------------------------------------------------
    // remove()
    // -------------------------------------------------------------------------

    public function testRemoveExistingKeyReturnsTrueAndKeyIsGone(): void
    {
        Config::getInstance($this->configFile);
        Config::set('key', 'value');

        $result = Config::remove('key');

        self::assertTrue($result);
        self::assertFalse(Config::has('key'));
    }

    public function testRemoveNonexistentKeyReturnsFalse(): void
    {
        Config::getInstance($this->configFile);
        self::assertFalse(Config::remove('nonexistent'));
    }

    public function testRemoveDoesNotAffectOtherKeys(): void
    {
        Config::getInstance($this->configFile);
        Config::set('a', 1);
        Config::set('b', 2);
        Config::remove('a');

        self::assertFalse(Config::has('a'));
        self::assertTrue(Config::has('b'));
        self::assertSame(2, Config::get('b'));
    }

    // -------------------------------------------------------------------------
    // save()
    // -------------------------------------------------------------------------

    public function testSaveWritesJsonFileAndReturnsTrueOnSuccess(): void
    {
        Config::getInstance($this->configFile);
        Config::set('x', 'saved');
        // set() already calls save(); call it again explicitly
        $result = Config::save();

        self::assertTrue($result);
        self::assertFileExists($this->configFile);
    }

    public function testSavedDataCanBeReloadedAfterStaticReset(): void
    {
        Config::getInstance($this->configFile);
        Config::set('reloaded', 'data');

        $this->resetConfig();
        Config::getInstance($this->configFile);

        self::assertSame('data', Config::get('reloaded'));
    }

    // -------------------------------------------------------------------------
    // __clone()
    // -------------------------------------------------------------------------

    public function testCloneViaSubclassExercisesProtectedCloneBody(): void
    {
        // CloneableConfig is a subclass, so its static method can clone the
        // instance — this exercises the otherwise-uncovered protected __clone() body.
        $cloned = CloneableConfig::cloneInstance($this->configFile);

        self::assertInstanceOf(Config::class, $cloned);
    }
}
