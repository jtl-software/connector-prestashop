<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\CustomerOrder;

use Carrier;
use Jtl\Connector\Core\Model\CustomerOrder as JtlCustomerOrder;
use Jtl\Connector\Core\Model\CustomerOrderItem;
use Order;

/**
 * Covers line items that are not regular products:
 *   - getShippingLineItem   (shipping fee → JTL item of type TYPE_SHIPPING)
 *   - addDiscountItems      (cart rules / coupons → JTL items of type TYPE_COUPON)
 */
final class CustomerOrderExtraLineItemTest extends CustomerOrderControllerTestCase
{
    // -------------------------------------------------------------------------
    // getShippingLineItem
    // -------------------------------------------------------------------------

    public function testShippingItemHasTypeShippingAndCarrierName(): void
    {
        $carrier       = new Carrier();
        $carrier->name = 'DHL Express';

        $result = $this->controller->exposeGetShippingLineItem(new Order(), $carrier);

        self::assertInstanceOf(CustomerOrderItem::class, $result);
        self::assertSame(CustomerOrderItem::TYPE_SHIPPING, $result->getType());
        self::assertSame('DHL Express', $result->getName());
    }

    public function testShippingItemDefaultCostsAreZeroAndQuantityIsOne(): void
    {
        $result = $this->controller->exposeGetShippingLineItem(new Order(), new Carrier());

        self::assertSame(0.0, $result->getPrice());
        self::assertSame(0.0, $result->getPriceGross());
        self::assertSame(1.0, $result->getQuantity());
    }

    public function testShippingItemTakesVatFromOrder(): void
    {
        $order                   = new Order();
        $order->carrier_tax_rate = 19.0;

        $result = $this->controller->exposeGetShippingLineItem($order, new Carrier());

        self::assertSame(19.0, $result->getVat());
    }

    public function testEmptyCarrierNameIsPassedThrough(): void
    {
        $carrier       = new Carrier();
        $carrier->name = '';

        $result = $this->controller->exposeGetShippingLineItem(new Order(), $carrier);

        self::assertSame('', $result->getName());
        self::assertSame(CustomerOrderItem::TYPE_SHIPPING, $result->getType());
    }

    // -------------------------------------------------------------------------
    // addDiscountItems
    // -------------------------------------------------------------------------

    public function testNoCartRulesProducesNoItems(): void
    {
        $order = new class extends Order {
            public function getCartRules(): array|int
            {
                return [];
            }
        };

        $jtlOrder = new JtlCustomerOrder();
        $this->controller->exposeAddDiscountItems($order, $jtlOrder);

        self::assertCount(0, $jtlOrder->getItems());
    }

    public function testSingleCartRuleWithoutCartRuleIdCreatesCouponItem(): void
    {
        $order = new class extends Order {
            public function getCartRules(): array|int
            {
                return [[
                    'id_order_cart_rule' => 1, 'id_order' => 10, 'id_cart_rule' => 0,
                    'id_order_invoice' => 0, 'name' => 'Summer Sale',
                    'value' => 5.0, 'value_tax_excl' => 4.2,
                    'free_shipping' => 0, 'deleted' => 0,
                ]];
            }
        };

        $jtlOrder = new JtlCustomerOrder();
        $this->controller->exposeAddDiscountItems($order, $jtlOrder);

        $items = $jtlOrder->getItems();
        self::assertCount(1, $items);

        $item = $items[0];
        self::assertSame(CustomerOrderItem::TYPE_COUPON, $item->getType());
        self::assertSame('rule_1', $item->getId()->getEndpoint());
        self::assertSame('Summer Sale', $item->getName());
        self::assertSame(-5.0, $item->getPriceGross());
        self::assertSame(-4.2, $item->getPrice());
        // VAT = round((5.0 / 4.2 - 1) * 100, 2) = 19.05
        self::assertSame(19.05, $item->getVat());
        self::assertSame(1.0, $item->getQuantity());
        self::assertSame('Code: ', $item->getNote());
    }

    public function testCartRuleWithCartRuleIdLoadsCodeForNote(): void
    {
        $order = new class extends Order {
            public function getCartRules(): array|int
            {
                return [[
                    'id_order_cart_rule' => 2, 'id_order' => 10, 'id_cart_rule' => 99,
                    'id_order_invoice' => 0, 'name' => 'Winter Promo',
                    'value' => 10.0, 'value_tax_excl' => 8.40,
                    'free_shipping' => 0, 'deleted' => 0,
                ]];
            }
        };

        $jtlOrder = new JtlCustomerOrder();
        $this->controller->exposeAddDiscountItems($order, $jtlOrder);

        $items = $jtlOrder->getItems();
        self::assertCount(1, $items);
        self::assertSame('Code: ', $items[0]->getNote());
        self::assertSame('Winter Promo', $items[0]->getName());
        self::assertSame(-10.0, $items[0]->getPriceGross());
        self::assertSame(-8.40, $items[0]->getPrice());
    }

    public function testMultipleCartRulesProduceOneItemEach(): void
    {
        $order = new class extends Order {
            public function getCartRules(): array|int
            {
                return [
                    ['id_order_cart_rule' => 10, 'id_order' => 5, 'id_cart_rule' => 0, 'id_order_invoice' => 0, 'name' => 'Discount A', 'value' => 3.0, 'value_tax_excl' => 2.52, 'free_shipping' => 0, 'deleted' => 0],
                    ['id_order_cart_rule' => 11, 'id_order' => 5, 'id_cart_rule' => 0, 'id_order_invoice' => 0, 'name' => 'Discount B', 'value' => 7.0, 'value_tax_excl' => 5.88, 'free_shipping' => 0, 'deleted' => 0],
                ];
            }
        };

        $jtlOrder = new JtlCustomerOrder();
        $this->controller->exposeAddDiscountItems($order, $jtlOrder);

        $items = $jtlOrder->getItems();
        self::assertCount(2, $items);
        self::assertSame('rule_10', $items[0]->getId()->getEndpoint());
        self::assertSame('rule_11', $items[1]->getId()->getEndpoint());
        self::assertSame('Discount A', $items[0]->getName());
        self::assertSame('Discount B', $items[1]->getName());
    }
}
