<?php

declare(strict_types=1);

namespace Tests\Support\Controller;

use Db;
use Jtl\Connector\Core\Model\Product as JtlProduct;
use jtl\Connector\Presta\Controller\ProductController;
use jtl\Connector\Presta\Mapper\PrimaryKeyMapper;
use Product as PrestaProduct;
use Psr\Log\LoggerInterface;

/**
 * Pushes products without the field mapping of updatePrestaProduct(), which needs a real shop, to test how pushed
 * products and variants are added and linked.
 */
final class PushableProductController extends ProductController
{
    /** Thrown by updatePrestaProduct(), i.e. after a new product was added and linked. */
    public ?\Throwable $failUpdatePrestaProduct = null;

    public function __construct(Db $db, PrimaryKeyMapper $mapper, LoggerInterface $logger)
    {
        $this->db             = $db;
        $this->mapper         = $mapper;
        $this->logger         = $logger;
        $this->controllerName = 'ProductController';
    }

    protected function updatePrestaProduct(JtlProduct $jtlProduct, PrestaProduct $prestaProduct): PrestaProduct
    {
        if ($this->failUpdatePrestaProduct !== null) {
            throw $this->failUpdatePrestaProduct;
        }
        $prestaProduct->id = (int)$jtlProduct->getId()->getEndpoint();

        return $prestaProduct;
    }

    protected function getPrestaContextShopId(): int
    {
        return 1;
    }
}
