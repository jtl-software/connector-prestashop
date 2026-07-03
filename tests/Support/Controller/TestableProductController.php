<?php

declare(strict_types=1);

namespace Tests\Support\Controller;

use Db;
use Jtl\Connector\Core\Model\Product as JtlProduct;
use jtl\Connector\Presta\Controller\ProductController;
use Product as PrestaProduct;
use Psr\Log\NullLogger;

/**
 * Overrides the constructor to avoid Db::getInstance() and stubs out the
 * DB-backed createJtl* sub-mappers so that createJtlProduct() can be
 * exercised in isolation, e.g. to verify stock-level mapping.
 */
final class TestableProductController extends ProductController
{
    public function __construct()
    {
        $this->db             = new Db();
        $this->logger         = new NullLogger();
        $this->controllerName = 'ProductController';
    }

    protected function getPrestaContextLanguageId(): int
    {
        return 1;
    }

    protected function getPrestaContextShopId(): int
    {
        return 1;
    }

    protected function createJtlSpecialPrices(PrestaProduct $prestaProduct): array
    {
        return [];
    }

    protected function createJtlSpecialAttributes(PrestaProduct $prestaProduct): array
    {
        return [];
    }

    protected function createJtlProductCategories(PrestaProduct $prestaProduct): array
    {
        return [];
    }

    protected function createJtlProductTranslations(int $prestaProductId): array
    {
        return [];
    }

    public function exposeCreateJtlProduct(PrestaProduct $prestaProduct): JtlProduct
    {
        return $this->createJtlProduct($prestaProduct);
    }
}
