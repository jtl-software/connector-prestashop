<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\CustomerOrder;

use Jtl\Connector\Core\Model\CustomerOrder as JtlCustomerOrder;
use Order;

/**
 * Covers setStates — the mapping from PrestaShop shipment flags to JTL order status.
 *
 * Business rules:
 *   - Payment status is always set to PAYMENT_STATUS_UNPAID (PS does not expose a paid flag here).
 *   - Order status is set to STATUS_SHIPPED if hasBeenShipped() OR hasBeenDelivered() returns 1.
 */
final class CustomerOrderStatusTest extends CustomerOrderControllerTestCase
{
    public function testPaymentStatusIsAlwaysUnpaid(): void
    {
        $jtlOrder = new JtlCustomerOrder();
        $this->controller->exposeSetStates(new Order(), $jtlOrder);

        self::assertSame(JtlCustomerOrder::PAYMENT_STATUS_UNPAID, $jtlOrder->getPaymentStatus());
    }

    public function testStatusIsNotShippedWhenNeitherShippedNorDelivered(): void
    {
        $jtlOrder = new JtlCustomerOrder();
        $this->controller->exposeSetStates(new Order(), $jtlOrder);

        self::assertNotSame(JtlCustomerOrder::STATUS_SHIPPED, $jtlOrder->getStatus());
    }

    public function testStatusIsShippedWhenHasBeenShipped(): void
    {
        $order = new class extends Order {
            public function hasBeenShipped(): int
            {
                return 1;
            }
        };

        $jtlOrder = new JtlCustomerOrder();
        $this->controller->exposeSetStates($order, $jtlOrder);

        self::assertSame(JtlCustomerOrder::STATUS_SHIPPED, $jtlOrder->getStatus());
    }

    public function testStatusIsShippedWhenHasBeenDelivered(): void
    {
        $order = new class extends Order {
            public function hasBeenDelivered(): int
            {
                return 1;
            }
        };

        $jtlOrder = new JtlCustomerOrder();
        $this->controller->exposeSetStates($order, $jtlOrder);

        self::assertSame(JtlCustomerOrder::STATUS_SHIPPED, $jtlOrder->getStatus());
    }

    public function testStatusIsShippedWhenBothShippedAndDelivered(): void
    {
        $order = new class extends Order {
            public function hasBeenShipped(): int
            {
                return 1;
            }
            public function hasBeenDelivered(): int
            {
                return 1;
            }
        };

        $jtlOrder = new JtlCustomerOrder();
        $this->controller->exposeSetStates($order, $jtlOrder);

        self::assertSame(JtlCustomerOrder::STATUS_SHIPPED, $jtlOrder->getStatus());
        self::assertSame(JtlCustomerOrder::PAYMENT_STATUS_UNPAID, $jtlOrder->getPaymentStatus());
    }
}
