<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Jtl\Connector\Core\Model\Product as JtlProduct;
use jtl\Connector\Presta\Controller\ProductStockLevelController;
use Db;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class TestableProductStockLevelController extends ProductStockLevelController
{
    public function __construct()
    {
        $this->db             = new Db();
        $this->logger         = new NullLogger();
        $this->controllerName = 'ProductStockLevelController';
    }
}

final class ProductStockLevelControllerTest extends TestCase
{
    private TestableProductStockLevelController $controller;

    protected function setUp(): void
    {
        Db::resetInstance();
        $this->controller = new TestableProductStockLevelController();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeProduct(string $endpoint, float $stockLevel = 0.0): JtlProduct
    {
        $product = new JtlProduct($endpoint);
        $product->setStockLevel($stockLevel);
        return $product;
    }

    // =========================================================================
    // push()
    // =========================================================================

    public function testPushWithEmptyEndpointReturnsModelUnchanged(): void
    {
        $product = $this->makeProduct('', 10.0);
        $result  = $this->controller->push($product);

        self::assertSame($product, $result);
        self::assertSame(10.0, $result->getStockLevel());
    }

    public function testPushWithSimpleProductEndpointReturnsModel(): void
    {
        $product = $this->makeProduct('123', 5.0);
        $result  = $this->controller->push($product);

        self::assertSame($product, $result);
    }

    public function testPushWithVariantEndpointReturnsModel(): void
    {
        $product = $this->makeProduct('123_456', 3.0);
        $result  = $this->controller->push($product);

        self::assertSame($product, $result);
    }

    public function testPushWithEndpointHavingZeroCombiReturnsModel(): void
    {
        // endpoint "123_0" → combiId = '0' which is empty-ish but cast to 0
        $product = $this->makeProduct('123_0', 7.0);
        $result  = $this->controller->push($product);

        self::assertSame($product, $result);
    }

    public function testPushWithZeroStockLevelReturnsModel(): void
    {
        $product = $this->makeProduct('99', 0.0);
        $result  = $this->controller->push($product);

        self::assertSame($product, $result);
    }

    // =========================================================================
    // pull()
    // =========================================================================

    public function testPullWithEmptyEndpointReturnsModelWithUnchangedStockLevel(): void
    {
        $product = $this->makeProduct('', 42.0);
        $result  = $this->controller->pull($product);

        self::assertSame($product, $result);
        // stockLevel must not have been modified
        self::assertSame(42.0, $result->getStockLevel());
    }

    public function testPullWithSimpleProductEndpointSetsStockLevelFromStub(): void
    {
        // StockAvailable stub always returns 0
        $product = $this->makeProduct('123', 99.0);
        $result  = $this->controller->pull($product);

        self::assertSame($product, $result);
        self::assertSame(0.0, $result->getStockLevel());
    }

    public function testPullWithVariantEndpointSetsStockLevelFromStub(): void
    {
        $product = $this->makeProduct('123_456', 50.0);
        $result  = $this->controller->pull($product);

        self::assertSame($product, $result);
        // StockAvailable::getQuantityAvailableByProduct stub always returns 0
        self::assertSame(0.0, $result->getStockLevel());
    }

    public function testPullWithEndpointWhereProductIdIsEmptyDoesNotSetStockLevel(): void
    {
        // An endpoint that explodes to an empty productId should leave stockLevel untouched
        // "_456" → productId = '' → condition !empty($productId) is false
        $product = $this->makeProduct('_456', 77.0);
        $result  = $this->controller->pull($product);

        self::assertSame($product, $result);
        self::assertSame(77.0, $result->getStockLevel());
    }

    public function testPullStockLevelIsIntCastFromStubReturn(): void
    {
        // Stub returns 0 (int); Product::setStockLevel casts via (int) in the controller
        $product = $this->makeProduct('5');
        /** @var JtlProduct $result */
        $result  = $this->controller->pull($product);

        self::assertSame(0.0, $result->getStockLevel());
    }
}
