<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\CustomerOrder;

use Cart;
use Context;
use Currency;
use Jtl\Connector\Core\Model\CustomerOrderItem;
use RuntimeException;

/**
 * Covers how PrestaShop cart products are mapped to JTL CustomerOrderItem objects.
 *
 * Tests:
 *   - createJtlCustomerOrderItem  (pure field mapping from a product data array)
 *   - getCustomerOrderItems       (cart iteration, context wiring, simple + variant products)
 */
final class CustomerOrderProductItemTest extends CustomerOrderControllerTestCase
{
    private function makeProductData(array $overrides = []): array
    {
        return array_merge([
            'id_product'                         => 5,
            'id_product_attribute'               => 0,
            'name'                               => 'Test Product',
            'price_with_reduction_without_tax'   => 9.99,
            'price_with_reduction'               => 11.89,
            'cart_quantity'                      => 2,
            'reference'                          => 'SKU-001',
            'rate'                               => 19.0,
            'attributes'                         => '',
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // createJtlCustomerOrderItem
    // -------------------------------------------------------------------------

    public function testSimpleProductSetsProductIdWithoutSuffix(): void
    {
        $result = $this->controller->exposeCreateJtlCustomerOrderItem(
            $this->makeProductData(['id_product' => 42, 'id_product_attribute' => 0])
        );

        self::assertSame('42', $result->getProductId()->getEndpoint());
        self::assertSame('Test Product', $result->getName());
        self::assertSame(CustomerOrderItem::TYPE_PRODUCT, $result->getType());
    }

    public function testVariantProductBuildsCompoundIdAndAppendAttributesToName(): void
    {
        $result = $this->controller->exposeCreateJtlCustomerOrderItem(
            $this->makeProductData(['id_product' => 10, 'id_product_attribute' => 3, 'name' => 'T-Shirt', 'attributes' => 'Size: L'])
        );

        self::assertSame('10_3', $result->getProductId()->getEndpoint());
        self::assertSame('T-Shirt | Size: L', $result->getName());
    }

    public function testEmptyStringAttributeTreatedAsSimpleProduct(): void
    {
        // empty string for id_product_attribute — the code checks (int) > 0, so '' → 0 → simple
        $result = $this->controller->exposeCreateJtlCustomerOrderItem(
            $this->makeProductData(['id_product' => 7, 'id_product_attribute' => ''])
        );

        self::assertSame('7', $result->getProductId()->getEndpoint());
        self::assertSame('Test Product', $result->getName());
    }

    public function testNumericFieldsMappedCorrectly(): void
    {
        $result = $this->controller->exposeCreateJtlCustomerOrderItem(
            $this->makeProductData([
                'price_with_reduction_without_tax' => 29.95,
                'price_with_reduction'             => 35.64,
                'cart_quantity'                    => 3,
                'reference'                        => 'REF-42',
                'rate'                             => 7.0,
            ])
        );

        self::assertSame(29.95, $result->getPrice());
        self::assertSame(35.64, $result->getPriceGross());
        self::assertSame(3.0, $result->getQuantity());
        self::assertSame('REF-42', $result->getSku());
        self::assertSame(7.0, $result->getVat());
    }

    // -------------------------------------------------------------------------
    // getCustomerOrderItems
    // -------------------------------------------------------------------------

    public function testThrowsWhenContextIsNull(): void
    {
        Context::resetContext();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Context is null');

        $this->controller->exposeGetCustomerOrderItems(new Cart());
    }

    public function testEmptyCartReturnsEmptyArray(): void
    {
        Context::setContext(new Context());

        self::assertSame([], $this->controller->exposeGetCustomerOrderItems(new Cart()));
    }

    public function testSimpleProductInCartReturnsMappedItem(): void
    {
        Context::setContext(new Context());

        $cart = new class(1) extends Cart {
            public function getProducts(bool $refresh = false, bool $delete = false, ?int $idCurrency = null, bool $fullInfos = false, bool $keepOrderPrices = false): array
            {
                return [[
                    'id_product' => 5, 'id_product_attribute' => 0,
                    'name' => 'Product A', 'price_with_reduction_without_tax' => 9.99,
                    'price_with_reduction' => 11.89, 'cart_quantity' => 2,
                    'reference' => 'SKU-001', 'rate' => 19.0, 'attributes' => '',
                ]];
            }
        };

        $result = $this->controller->exposeGetCustomerOrderItems($cart);

        self::assertCount(1, $result);
        self::assertSame('5', $result[0]->getProductId()->getEndpoint());
        self::assertSame('Product A', $result[0]->getName());
    }

    public function testVariantProductInCartBuildsCompoundId(): void
    {
        Context::setContext(new Context());

        $cart = new class(2) extends Cart {
            public function getProducts(bool $refresh = false, bool $delete = false, ?int $idCurrency = null, bool $fullInfos = false, bool $keepOrderPrices = false): array
            {
                return [[
                    'id_product' => 10, 'id_product_attribute' => 3,
                    'name' => 'T-Shirt', 'price_with_reduction_without_tax' => 19.95,
                    'price_with_reduction' => 23.74, 'cart_quantity' => 1,
                    'reference' => 'TS-L', 'rate' => 19.0, 'attributes' => 'Size: L',
                ]];
            }
        };

        $result = $this->controller->exposeGetCustomerOrderItems($cart);

        self::assertCount(1, $result);
        self::assertSame('10_3', $result[0]->getProductId()->getEndpoint());
        self::assertSame('T-Shirt | Size: L', $result[0]->getName());
    }

    public function testMultipleProductsInCartAllReturned(): void
    {
        Context::setContext(new Context());

        $cart = new class(3) extends Cart {
            public function getProducts(bool $refresh = false, bool $delete = false, ?int $idCurrency = null, bool $fullInfos = false, bool $keepOrderPrices = false): array
            {
                return [
                    ['id_product' => 1, 'id_product_attribute' => 0, 'name' => 'First',  'price_with_reduction_without_tax' => 5.0,  'price_with_reduction' => 5.95,  'cart_quantity' => 1, 'reference' => 'A', 'rate' => 19.0, 'attributes' => ''],
                    ['id_product' => 2, 'id_product_attribute' => 0, 'name' => 'Second', 'price_with_reduction_without_tax' => 10.0, 'price_with_reduction' => 11.90, 'cart_quantity' => 3, 'reference' => 'B', 'rate' => 19.0, 'attributes' => ''],
                ];
            }
        };

        $result = $this->controller->exposeGetCustomerOrderItems($cart);

        self::assertCount(2, $result);
        self::assertSame('1', $result[0]->getProductId()->getEndpoint());
        self::assertSame('2', $result[1]->getProductId()->getEndpoint());
    }

    public function testSetsContextCartAndCurrencyAfterCall(): void
    {
        $ctx = new Context();
        Context::setContext($ctx);

        $cart              = new Cart(7);
        $cart->id_currency = 3;

        $this->controller->exposeGetCustomerOrderItems($cart);

        self::assertSame($cart, $ctx->cart);
        self::assertInstanceOf(Currency::class, $ctx->currency);
    }
}
