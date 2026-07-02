<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\CustomerOrder;

use Context;
use Customer;
use DateTimeInterface;
use Db;
use Jtl\Connector\Core\Model\CustomerOrder as JtlCustomerOrder;
use Jtl\Connector\Core\Model\CustomerOrderBillingAddress as JtlBillingAddress;
use Jtl\Connector\Core\Model\CustomerOrderItem as JtlCustomerOrderItem;
use Jtl\Connector\Core\Model\CustomerOrderShippingAddress as JtlShippingAddress;
use Order;
use RuntimeException;

/**
 * Covers createJtlCustomerOrder — the full assembly of a JTL order from a
 * PrestaShop Order object (addresses, items, shipping, discounts, metadata).
 */
final class CustomerOrderCreationTest extends CustomerOrderControllerTestCase
{
    private function makeOrder(array $overrides = []): Order
    {
        $order                      = new Order(5);
        $order->id_currency         = 1;
        $order->id_carrier          = 1;
        $order->id_customer         = 1;
        $order->id_address_invoice  = 1;
        $order->id_address_delivery = 1;
        $order->id_cart             = 1;
        $order->id_lang             = 1;
        $order->reference           = 'TEST-001';
        $order->module              = 'paypal';
        $order->date_add            = '2024-01-15 10:00:00';
        $order->invoice_date        = '2024-01-15 10:05:00';
        $order->delivery_date       = '2024-01-16 12:00:00';
        $order->total_paid          = 119.0;
        $order->total_paid_tax_incl = 119.0;
        foreach ($overrides as $k => $v) {
            $order->$k = $v;
        }
        return $order;
    }

    private function prepareController(string $referenceCount = '1'): void
    {
        $db = $this->createMock(Db::class);
        $db->method('getValue')->willReturn($referenceCount);
        $this->controller->injectDb($db);

        Context::setContext(new Context());

        $this->controller->setMockOrderItems([]);
        $this->controller->setMockShippingItem(
            (new JtlCustomerOrderItem())
                ->setName('Standard Shipping')
                ->setType(JtlCustomerOrderItem::TYPE_SHIPPING)
                ->setPrice(0.0)
                ->setPriceGross(0.0)
                ->setQuantity(1.0)
        );
    }

    // -------------------------------------------------------------------------
    // Guard clauses
    // -------------------------------------------------------------------------

    public function testThrowsWhenOrderIdIsNull(): void
    {
        $this->prepareController();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Presta Order id can't be null");

        $this->controller->exposeCreateJtlCustomerOrder(new Order(null));
    }

    public function testThrowsWhenCustomerIdIsZero(): void
    {
        $this->prepareController();

        $order              = $this->makeOrder();
        $order->id_customer = 0;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Can't load Customer from Order");

        $this->controller->exposeCreateJtlCustomerOrder($order);
    }

    public function testThrowsWhenLoadedCustomerIdDoesNotMatchOrderCustomerId(): void
    {
        $this->prepareController();

        // Force all Customer constructions to return id=999; order has id_customer=1
        Customer::$mockIdOverride = 999;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/does not match/');

        $this->controller->exposeCreateJtlCustomerOrder($this->makeOrder());
    }

    // -------------------------------------------------------------------------
    // Identity fields
    // -------------------------------------------------------------------------

    public function testReturnsJtlCustomerOrderInstance(): void
    {
        $this->prepareController();
        self::assertInstanceOf(JtlCustomerOrder::class, $this->controller->exposeCreateJtlCustomerOrder($this->makeOrder()));
    }

    public function testSetsOrderIdFromPrestaOrderId(): void
    {
        $this->prepareController();
        $result = $this->controller->exposeCreateJtlCustomerOrder($this->makeOrder());

        self::assertSame('5', $result->getId()->getEndpoint());
    }

    public function testSetsCustomerIdFromOrder(): void
    {
        $this->prepareController();
        $result = $this->controller->exposeCreateJtlCustomerOrder($this->makeOrder());

        self::assertSame('1', $result->getCustomerId()->getEndpoint());
    }

    public function testSetsCurrencyIsoFromCurrencyStub(): void
    {
        $this->prepareController();
        $result = $this->controller->exposeCreateJtlCustomerOrder($this->makeOrder());

        self::assertSame('EUR', $result->getCurrencyIso());
    }

    public function testSetsLanguageIsoFromStub(): void
    {
        // TestableCustomerOrderController::getJtlLanguageIsoFromLanguageId always returns 'eng'
        $this->prepareController();
        $result = $this->controller->exposeCreateJtlCustomerOrder($this->makeOrder());

        self::assertSame('eng', $result->getLanguageIso());
    }

    // -------------------------------------------------------------------------
    // Order number (reference uniqueness)
    // -------------------------------------------------------------------------

    public function testOrderNumberEqualsReferenceWhenUnique(): void
    {
        $this->prepareController(referenceCount: '1');
        $result = $this->controller->exposeCreateJtlCustomerOrder($this->makeOrder(['reference' => 'REF-XYZ']));

        self::assertSame('REF-XYZ', $result->getOrderNumber());
    }

    public function testOrderNumberGetsSuffixWhenReferenceIsDuplicated(): void
    {
        $this->prepareController(referenceCount: '2');
        $result = $this->controller->exposeCreateJtlCustomerOrder($this->makeOrder(['reference' => 'DUP-001']));

        // Order id = 5, so duplicate suffix is '-5'
        self::assertSame('DUP-001-5', $result->getOrderNumber());
    }

    // -------------------------------------------------------------------------
    // Financial fields
    // -------------------------------------------------------------------------

    public function testSetsTotalSumAndTotalSumGross(): void
    {
        $this->prepareController();
        $result = $this->controller->exposeCreateJtlCustomerOrder(
            $this->makeOrder(['total_paid' => 99.50, 'total_paid_tax_incl' => 118.41])
        );

        self::assertSame(99.50, $result->getTotalSum());
        self::assertSame(118.41, $result->getTotalSumGross());
    }

    public function testSetsPaymentModuleCode(): void
    {
        $this->prepareController();
        $result = $this->controller->exposeCreateJtlCustomerOrder($this->makeOrder(['module' => 'paypal']));

        self::assertNotEmpty($result->getPaymentModuleCode());
    }

    // -------------------------------------------------------------------------
    // Carrier / shipping
    // -------------------------------------------------------------------------

    public function testSetsShippingMethodNameFromCarrier(): void
    {
        // Carrier stub: name = '' by default
        $this->prepareController();
        $result = $this->controller->exposeCreateJtlCustomerOrder($this->makeOrder());

        self::assertSame('', $result->getShippingMethodName());
    }

    public function testShippingItemIsPresentInItems(): void
    {
        $this->prepareController();
        $result = $this->controller->exposeCreateJtlCustomerOrder($this->makeOrder());

        $shippingItems = array_values(array_filter(
            $result->getItems(),
            static fn(JtlCustomerOrderItem $i) => $i->getType() === JtlCustomerOrderItem::TYPE_SHIPPING
        ));

        self::assertCount(1, $shippingItems);
    }

    public function testProductItemsAndShippingItemAreBothPresent(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('getValue')->willReturn('1');
        $this->controller->injectDb($db);
        Context::setContext(new Context());

        $this->controller->setMockOrderItems([
            (new JtlCustomerOrderItem())->setName('Widget')->setType(JtlCustomerOrderItem::TYPE_PRODUCT)->setPrice(10.0)->setPriceGross(11.90)->setQuantity(2.0),
        ]);
        $this->controller->setMockShippingItem(
            (new JtlCustomerOrderItem())->setName('Standard Shipping')->setType(JtlCustomerOrderItem::TYPE_SHIPPING)->setPrice(5.0)->setPriceGross(5.95)->setQuantity(1.0)
        );

        $result = $this->controller->exposeCreateJtlCustomerOrder($this->makeOrder());
        $items  = $result->getItems();

        self::assertCount(2, $items);
        $productItems  = array_values(array_filter($items, static fn(JtlCustomerOrderItem $i) => $i->getType() === JtlCustomerOrderItem::TYPE_PRODUCT));
        $shippingItems = array_values(array_filter($items, static fn(JtlCustomerOrderItem $i) => $i->getType() === JtlCustomerOrderItem::TYPE_SHIPPING));
        self::assertSame('Widget', $productItems[0]->getName());
        self::assertSame('Standard Shipping', $shippingItems[0]->getName());
    }

    public function testDiscountItemsAddedWhenCartRulesExist(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('getValue')->willReturn('1');
        $this->controller->injectDb($db);
        Context::setContext(new Context());
        $this->controller->setMockOrderItems([]);
        $this->controller->setMockShippingItem(
            (new JtlCustomerOrderItem())->setName('Shipping')->setType(JtlCustomerOrderItem::TYPE_SHIPPING)->setPrice(0.0)->setPriceGross(0.0)->setQuantity(1.0)
        );

        $order = new class(5) extends Order {
            public function getCartRules(): array|int
            {
                return [[
                    'id_order_cart_rule' => 7, 'id_order' => 5, 'id_cart_rule' => 0,
                    'id_order_invoice' => 0, 'name' => 'Flash Sale',
                    'value' => 5.0, 'value_tax_excl' => 4.20,
                    'free_shipping' => 0, 'deleted' => 0,
                ]];
            }
        };
        $order->id_currency = 1; $order->id_carrier = 1; $order->id_customer = 1;
        $order->id_address_invoice = 1; $order->id_address_delivery = 1;
        $order->id_cart = 1; $order->id_lang = 1;
        $order->reference = 'PROMO-01'; $order->module = 'paypal';
        $order->date_add = '2024-03-01 08:00:00'; $order->invoice_date = '2024-03-01 08:05:00';
        $order->delivery_date = '2024-03-02 14:00:00';
        $order->total_paid = 114.0; $order->total_paid_tax_incl = 114.0;

        $result = $this->controller->exposeCreateJtlCustomerOrder($order);

        $couponItems = array_values(array_filter(
            $result->getItems(),
            static fn(JtlCustomerOrderItem $i) => $i->getType() === JtlCustomerOrderItem::TYPE_COUPON
        ));

        self::assertCount(1, $couponItems);
        self::assertSame('Flash Sale', $couponItems[0]->getName());
        self::assertSame(-5.0, $couponItems[0]->getPriceGross());
        self::assertSame('rule_7', $couponItems[0]->getId()->getEndpoint());
    }

    // -------------------------------------------------------------------------
    // Dates
    // -------------------------------------------------------------------------

    public function testSetsCreationDateFromDateAdd(): void
    {
        $this->prepareController();
        $result = $this->controller->exposeCreateJtlCustomerOrder($this->makeOrder(['date_add' => '2024-06-01 09:30:00']));

        $creationDate = $result->getCreationDate();
        self::assertInstanceOf(DateTimeInterface::class, $creationDate);
        self::assertSame('2024-06-01', $creationDate->format('Y-m-d'));
    }

    // -------------------------------------------------------------------------
    // Note
    // -------------------------------------------------------------------------

    public function testSetsNoteFromFirstMessage(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('getValue')->willReturn('1');
        $this->controller->injectDb($db);
        Context::setContext(new Context());
        $this->controller->setMockOrderItems([]);
        $this->controller->setMockShippingItem(
            (new JtlCustomerOrderItem())->setName('Shipping')->setType(JtlCustomerOrderItem::TYPE_SHIPPING)->setPrice(0.0)->setPriceGross(0.0)->setQuantity(1.0)
        );

        $order = new class(5) extends Order {
            public function getFirstMessage(): ?string
            {
                return 'Please leave at door';
            }
        };
        $order->id_currency = 1; $order->id_carrier = 1; $order->id_customer = 1;
        $order->id_address_invoice = 1; $order->id_address_delivery = 1;
        $order->id_cart = 1; $order->id_lang = 1;
        $order->reference = 'NOTE-001'; $order->module = 'paypal';
        $order->date_add = '2024-01-15 10:00:00'; $order->invoice_date = '2024-01-15 10:05:00';
        $order->delivery_date = '2024-01-16 12:00:00';
        $order->total_paid = 100.0; $order->total_paid_tax_incl = 119.0;

        self::assertSame('Please leave at door', $this->controller->exposeCreateJtlCustomerOrder($order)->getNote());
    }

    public function testNoteIsEmptyWhenNoFirstMessage(): void
    {
        $this->prepareController();
        $result = $this->controller->exposeCreateJtlCustomerOrder($this->makeOrder());

        self::assertSame('', $result->getNote());
    }

    // -------------------------------------------------------------------------
    // Addresses
    // -------------------------------------------------------------------------

    public function testSetsBillingAddress(): void
    {
        $this->prepareController();
        $result = $this->controller->exposeCreateJtlCustomerOrder($this->makeOrder());

        self::assertInstanceOf(JtlBillingAddress::class, $result->getBillingAddress());
    }

    public function testSetsShippingAddress(): void
    {
        $this->prepareController();
        $result = $this->controller->exposeCreateJtlCustomerOrder($this->makeOrder());

        self::assertInstanceOf(JtlShippingAddress::class, $result->getShippingAddress());
    }

    // -------------------------------------------------------------------------
    // Shipping tracking number
    // -------------------------------------------------------------------------

    public function testSetsShippingInfoFromShippingNumber(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('getValue')->willReturn('1');
        $this->controller->injectDb($db);
        Context::setContext(new Context());
        $this->controller->setMockOrderItems([]);
        $this->controller->setMockShippingItem(
            (new JtlCustomerOrderItem())->setName('Shipping')->setType(JtlCustomerOrderItem::TYPE_SHIPPING)->setPrice(0.0)->setPriceGross(0.0)->setQuantity(1.0)
        );

        $order = new class(5) extends Order {
            public function getShippingNumber(): ?string
            {
                return 'TRACK-123456';
            }
        };
        $order->id_currency = 1; $order->id_carrier = 1; $order->id_customer = 1;
        $order->id_address_invoice = 1; $order->id_address_delivery = 1;
        $order->id_cart = 1; $order->id_lang = 1;
        $order->reference = 'SHIP-001'; $order->module = 'paypal';
        $order->date_add = '2024-01-15 10:00:00'; $order->invoice_date = '2024-01-15 10:05:00';
        $order->delivery_date = '2024-01-16 12:00:00';
        $order->total_paid = 100.0; $order->total_paid_tax_incl = 119.0;

        self::assertSame('TRACK-123456', $this->controller->exposeCreateJtlCustomerOrder($order)->getShippingInfo());
    }
}
