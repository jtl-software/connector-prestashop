<?php

namespace jtl\Connector\Presta;

use Composer\InstalledVersions;
use DI\Container;
use Jtl\Connector\Core\Authentication\TokenValidatorInterface;
use Jtl\Connector\Core\Connector\ConnectorInterface;
use Jtl\Connector\Core\Mapper\PrimaryKeyMapperInterface;
use jtl\Connector\Core\Rpc\RequestPacket;
use jtl\Connector\Core\Utilities\RpcMethod;
use jtl\Connector\Base\Connector as BaseConnector;
use jtl\Connector\Model\Product;
use jtl\Connector\Presta\Mapper\PrimaryKeyMapper;
use jtl\Connector\Result\Action;
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

    public function canHandle()
    {
        $controller = RpcMethod::buildController($this->getMethod()->getController());
        $class      = "\\jtl\\Connector\\Connector\\Controller\\{$controller}";

        if (\class_exists($class)) {
            $this->controller = $class::getInstance();
            $this->action     = RpcMethod::buildAction($this->getMethod()->getAction());

            return \is_callable([$this->controller, $this->action]);
        }

        return false;
    }

    public function handle(RequestPacket $requestpacket)
    {
        if (!empty(\Db::getInstance()->executeS('SHOW TABLES LIKE "jtl_connector_link"'))) {
            throw new \RuntimeException(
                "Detected old linking table, please upgrade your connector in the Prestashop backend!"
            );
        }

        $this->controller->setMethod($this->getMethod());

        $actionExceptions = [
            'pull',
            'statistic',
            'identify',
        ];

        $callExceptions = [
            //'image.push'
        ];

        if (!\in_array($this->action, $actionExceptions) && !\in_array($requestpacket->getMethod(), $callExceptions)) {
            if (!\is_array($requestpacket->getParams())) {
                throw new \Exception('data is not an array');
            }

            $action  = new Action();
            $results = [];
            $link    = \Db::getInstance()->getLink();

            if ($link instanceof \PDO) {
                $link->beginTransaction();
            } elseif ($link instanceof \mysqli) {
                $link->begin_transaction();
            }

            if (\method_exists($this->controller, 'initPush')) {
                $this->controller->initPush($requestpacket->getParams());
            }

            foreach ($requestpacket->getParams() as $param) {
                $currentItem = $param;
                $result      = $this->controller->{$this->action}($param);

                if ($result->getError()) {
                    \Db::getInstance()->getLink()->rollback();
                    if (\method_exists($currentItem, 'getId')) {
                        if ($currentItem instanceof Product) {
                            throw new \Exception(
                                \sprintf(
                                    'Type: Product Host-Id: %s SKU: %s %s',
                                    $currentItem->getId()->getHost(),
                                    $currentItem->getSku(),
                                    $result->getError()->getMessage()
                                )
                            );
                        } else {
                            throw new \Exception(
                                \sprintf(
                                    'Type: %s Host-Id: %s %s',
                                    \get_class($currentItem),
                                    $currentItem->getId()->getHost(),
                                    $result->getError()->getMessage()
                                )
                            );
                        }
                    }

                    throw new \Exception(
                        \sprintf('Type: %s %s', \get_class($currentItem), $result->getError()->getMessage())
                    );
                }

                $results[] = $result->getResult();
            }

            if (\method_exists($this->controller, 'finishPush')) {
                $this->controller->finishPush($requestpacket->getParams(), $results);
            }

            \Db::getInstance()->getLink()->commit();

            $action->setHandled(true)
                ->setResult($results)
                ->setError($result->getError());

            return $action;
        }

        return $this->controller->{$this->action}($requestpacket->getParams());
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
