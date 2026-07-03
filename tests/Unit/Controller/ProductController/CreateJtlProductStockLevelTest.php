<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\ProductController;

use PHPUnit\Framework\TestCase;
use Product as PrestaProduct;
use StockAvailable;
use Tests\Support\Controller\TestableProductController;

/**
 * createJtlProduct() must always map the current stock level, regardless of
 * any shop-level setting, since PrestaShop has no configuration to disable
 * stock synchronisation. Regression test for a bug where the stock quantity
 * was read from `new StockAvailable($productId)`, which loads a row by the
 * unrelated primary key `id_stock_available` instead of by `id_product` and
 * therefore returned wrong (usually zero) data for master products.
 */
final class CreateJtlProductStockLevelTest extends TestCase
{
    protected function setUp(): void
    {
        StockAvailable::resetMock();
    }

    protected function tearDown(): void
    {
        StockAvailable::resetMock();
    }

    public function testMapsStockLevelFromStockAvailableByProduct(): void
    {
        StockAvailable::$mockQuantities = [42 => 300];
        $controller = new TestableProductController();

        $result = $controller->exposeCreateJtlProduct(new PrestaProduct(42));

        self::assertSame(300.0, $result->getStockLevel());
    }

    public function testMapsZeroStockLevel(): void
    {
        StockAvailable::$mockQuantities = [42 => 0];
        $controller = new TestableProductController();

        $result = $controller->exposeCreateJtlProduct(new PrestaProduct(42));

        self::assertSame(0.0, $result->getStockLevel());
    }

    public function testUsesTheGivenProductIdToLookUpStock(): void
    {
        // Guards the id used to look up the quantity: it must be the product id
        // passed to createJtlProduct(), not some other, unrelated id.
        StockAvailable::$mockQuantities = [42 => 300, 99 => 5];
        $controller = new TestableProductController();

        $result = $controller->exposeCreateJtlProduct(new PrestaProduct(42));

        self::assertSame(300.0, $result->getStockLevel());
    }

    public function testConsidersStockWhenOutOfStockDenied(): void
    {
        StockAvailable::$mockOutOfStock = [42 => 0];
        $controller = new TestableProductController();

        $result = $controller->exposeCreateJtlProduct(new PrestaProduct(42));

        self::assertTrue($result->getConsiderStock());
        self::assertTrue($result->getPermitNegativeStock());
    }

    public function testConsidersStockWhenOutOfStockUsesGlobalSetting(): void
    {
        StockAvailable::$mockOutOfStock = [42 => 2];
        $controller = new TestableProductController();

        $result = $controller->exposeCreateJtlProduct(new PrestaProduct(42));

        self::assertTrue($result->getConsiderStock());
        self::assertFalse($result->getPermitNegativeStock());
    }

    public function testDoesNotConsiderStockWhenOrdersAreAlwaysAllowed(): void
    {
        StockAvailable::$mockOutOfStock = [42 => 1];
        $controller = new TestableProductController();

        $result = $controller->exposeCreateJtlProduct(new PrestaProduct(42));

        self::assertFalse($result->getConsiderStock());
        self::assertFalse($result->getPermitNegativeStock());
    }

    public function testTreatsOutOfStockFalseReturnAsDenyOrders(): void
    {
        // Real PrestaShop's StockAvailable::outOfStock() returns bool false for an
        // invalid product id; the (int) cast in the controller must not crash on it
        // and must fall back to the "deny orders" (0) semantics.
        StockAvailable::$mockOutOfStock = [42 => false];
        $controller = new TestableProductController();

        $result = $controller->exposeCreateJtlProduct(new PrestaProduct(42));

        self::assertTrue($result->getConsiderStock());
        self::assertTrue($result->getPermitNegativeStock());
    }
}
