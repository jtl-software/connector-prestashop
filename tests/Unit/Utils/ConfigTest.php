<?php

declare(strict_types=1);

namespace Tests\Unit\Utils;

use jtl\Connector\Presta\Utils\Config;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \jtl\Connector\Presta\Utils\Config
 */
class ConfigTest extends TestCase
{
    /**
     * Reset the singleton's static state so every test starts from a clean slate.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $reflection = new ReflectionClass(Config::class);

        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        $data = $reflection->getProperty('data');
        $data->setAccessible(true);
        $data->setValue(null, null);

        $configFile = \CONNECTOR_DIR . '/config/config.json';
        if (\is_file($configFile)) {
            \unlink($configFile);
        }
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $configFile = \CONNECTOR_DIR . '/config/config.json';
        if (\is_file($configFile)) {
            \unlink($configFile);
        }

        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testGetInstanceReturnsSingleton(): void
    {
        $this->assertSame(Config::getInstance(), Config::getInstance());
    }

    /**
     * @return void
     */
    public function testGetDataReturnsStdClassWhenNoConfigFileExists(): void
    {
        $data = Config::getData();

        $this->assertInstanceOf(\stdClass::class, $data);
    }

    /**
     * @return void
     */
    public function testSetPersistsAndGetReturnsValue(): void
    {
        Config::set('token', 'secret-value');

        $this->assertTrue(Config::has('token'));
        $this->assertSame('secret-value', Config::get('token'));
    }

    /**
     * @return void
     */
    public function testSetWritesConfigFileToDisk(): void
    {
        Config::set('foo', 'bar');

        $configFile = \CONNECTOR_DIR . '/config/config.json';
        $this->assertFileExists($configFile);

        $decoded = \json_decode((string)\file_get_contents($configFile), true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('foo', $decoded);
        $this->assertSame('bar', $decoded['foo']);
    }

    /**
     * @return void
     */
    public function testHasReturnsFalseForUnknownKey(): void
    {
        $this->assertFalse(Config::has('does-not-exist'));
    }

    /**
     * @return void
     */
    public function testRemoveDeletesExistingKey(): void
    {
        Config::set('temporary', 123);
        $this->assertTrue(Config::has('temporary'));

        $this->assertTrue(Config::remove('temporary'));
        $this->assertFalse(Config::has('temporary'));
    }

    /**
     * @return void
     */
    public function testRemoveReturnsFalseForUnknownKey(): void
    {
        $this->assertFalse(Config::remove('does-not-exist'));
    }

    /**
     * @return void
     */
    public function testGetInstanceLoadsDataFromGivenFile(): void
    {
        $file = \sys_get_temp_dir() . '/jtlconnector-config-' . \uniqid() . '.json';
        \file_put_contents($file, \json_encode(['host' => 'example.org', 'port' => 443]));

        try {
            Config::getInstance($file);

            $this->assertTrue(Config::has('host'));
            $this->assertSame('example.org', Config::get('host'));
            $this->assertSame(443, Config::get('port'));
        } finally {
            \unlink($file);
        }
    }

    /**
     * @return void
     */
    public function testGetInstanceFallsBackToEmptyDataForInvalidJson(): void
    {
        $file = \sys_get_temp_dir() . '/jtlconnector-config-' . \uniqid() . '.json';
        \file_put_contents($file, 'this-is-not-json');

        try {
            Config::getInstance($file);

            $this->assertInstanceOf(\stdClass::class, Config::getData());
            $this->assertFalse(Config::has('anything'));
        } finally {
            \unlink($file);
        }
    }
}
