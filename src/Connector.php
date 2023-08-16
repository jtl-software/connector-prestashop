<?php

namespace jtl\Connector\Presta;

use Composer\InstalledVersions;
use DI\Container;
use Jtl\Connector\Core\Authentication\TokenValidatorInterface;
use Jtl\Connector\Core\Connector\ConnectorInterface;
use Jtl\Connector\Core\Mapper\PrimaryKeyMapperInterface;
use jtl\Connector\Presta\Mapper\PrimaryKeyMapper;
use jtl\Connector\Presta\Auth\TokenValidator;
use jtl\Connector\Presta\Checksum\ChecksumLoader;
use Noodlehaus\ConfigInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

class Connector implements ConnectorInterface
{
    /**
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    public function initialize(ConfigInterface $config, Container $container, EventDispatcher $dispatcher): void
    {
        $this->container = $container;

        $this->container->set(
            PrimaryKeyMapper::class,
            fn(ContainerInterface $container) => new PrimaryKeyMapper()
        );
        $this->container->set(
            ChecksumLoader::class,
            fn(ContainerInterface $container) => new ChecksumLoader()
        );
        $this->container->set(
            TokenValidator::class,
            fn(ContainerInterface $container) => new TokenValidator(\Configuration::get('jtlconnector_pass'))
        );
    }

    public function getPrimaryKeyMapper(): PrimaryKeyMapperInterface
    {
        return $this->container->get(PrimaryKeyMapper::class);
    }

    public function getTokenValidator(): TokenValidatorInterface
    {
        return $this->container->get(TokenValidator::class);
    }

    public function getControllerNamespace(): string
    {
        return __NAMESPACE__ . '\Controller';
    }

    public function getEndpointVersion(): string
    {
        return InstalledVersions::getPrettyVersion('jtl/connector-prestashop');
    }

    public function getPlatformVersion(): string
    {
        return '';
    }

    public function getPlatformName(): string
    {
        return 'Prestashop';
    }
}
