<?php

declare(strict_types=1);

namespace Tests\Unit;

use Configuration;
use DI\Container;
use jtl\Connector\Presta\Auth\TokenValidator;
use jtl\Connector\Presta\Connector;
use jtl\Connector\Presta\Mapper\PrimaryKeyMapper;
use Noodlehaus\ConfigInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Exposes the protected $container property for injection without calling initialize().
 */
final class TestableConnector extends Connector
{
    public function injectContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }
}

final class ConnectorTest extends TestCase
{
    protected function setUp(): void
    {
        Configuration::resetAll();
    }

    // -------------------------------------------------------------------------
    // getControllerNamespace
    // -------------------------------------------------------------------------

    public function testGetControllerNamespaceReturnsExpectedNamespace(): void
    {
        $connector = new Connector();

        self::assertSame('jtl\Connector\Presta\Controller', $connector->getControllerNamespace());
    }

    // -------------------------------------------------------------------------
    // getPlatformVersion / getPlatformName
    // -------------------------------------------------------------------------

    public function testGetPlatformVersionReturnsOne(): void
    {
        $connector = new Connector();

        self::assertSame('1', $connector->getPlatformVersion());
    }

    public function testGetPlatformNameReturnsPrestashop(): void
    {
        $connector = new Connector();

        self::assertSame('Prestashop', $connector->getPlatformName());
    }

    // -------------------------------------------------------------------------
    // getEndpointVersion
    // -------------------------------------------------------------------------

    public function testGetEndpointVersionReturnsVersionFromBuildConfigYaml(): void
    {
        $connector = new Connector();

        // build-config.yaml in the project root defines version: 2.0.2
        self::assertSame('2.0.2', $connector->getEndpointVersion());
    }

    // -------------------------------------------------------------------------
    // initialize
    // -------------------------------------------------------------------------

    public function testInitializeRegistersAllContainerBindings(): void
    {
        $connector  = new Connector();
        $container  = $this->createMock(Container::class);
        $config     = $this->createMock(ConfigInterface::class);
        $dispatcher = new EventDispatcher();

        // Expect exactly 8 set() calls:
        // PrimaryKeyMapper, TokenValidator, Product, Category, Image, Customer, Manufacturer, ProductStockLevel
        $container->expects(self::exactly(8))->method('set');

        Configuration::set('jtlconnector_pass', 'testtoken');

        $connector->initialize($config, $container, $dispatcher);
    }

    // -------------------------------------------------------------------------
    // getPrimaryKeyMapper
    // -------------------------------------------------------------------------

    public function testGetPrimaryKeyMapperReturnsMappedInstanceFromContainer(): void
    {
        $connector = new TestableConnector();
        $mapper    = $this->createMock(PrimaryKeyMapper::class);

        $container = $this->createMock(ContainerInterface::class);
        $container
            ->method('get')
            ->with(PrimaryKeyMapper::class)
            ->willReturn($mapper);

        $connector->injectContainer($container);

        self::assertSame($mapper, $connector->getPrimaryKeyMapper());
    }

    // -------------------------------------------------------------------------
    // getTokenValidator
    // -------------------------------------------------------------------------

    public function testGetTokenValidatorReturnsValidatorInstanceFromContainer(): void
    {
        $connector = new TestableConnector();
        $validator = $this->createMock(TokenValidator::class);

        $container = $this->createMock(ContainerInterface::class);
        $container
            ->method('get')
            ->with(TokenValidator::class)
            ->willReturn($validator);

        $connector->injectContainer($container);

        self::assertSame($validator, $connector->getTokenValidator());
    }
}
