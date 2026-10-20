<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\CustomerOrder;

use Configuration;
use Db;
use Jtl\Connector\Core\Model\CustomerOrder as JtlCustomerOrder;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\QueryFilter;

/**
 * Covers pull() — fetching unlinked PrestaShop orders and converting them to JTL CustomerOrder objects.
 *
 * Branches:
 *   - No orders in DB → empty result.
 *   - With / without soft-delete `deleted` column in ps_orders.
 *   - With / without `jtlconnector_from_date` filtering.
 *   - With orders in DB → mapped JtlCustomerOrder instances returned.
 */
final class CustomerOrderPullTest extends CustomerOrderControllerTestCase
{
    public function testNoOrdersReturnsEmptyArray(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturnOnConsecutiveCalls([], []);
        $db->method('getValue')->willReturn('0');
        $this->controller->injectDb($db);

        Configuration::set('jtlconnector_from_date', '');

        self::assertSame([], $this->controller->pull(new QueryFilter()));
    }

    public function testNoOrdersWithDeletedColumnReturnsEmptyArray(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturnOnConsecutiveCalls([['Field' => 'deleted']], []);
        $db->method('getValue')->willReturn('0');
        $this->controller->injectDb($db);

        Configuration::set('jtlconnector_from_date', '');

        self::assertSame([], $this->controller->pull(new QueryFilter()));
    }

    public function testWithFromDateAndNoOrdersReturnsEmptyArray(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturnOnConsecutiveCalls([], []);
        $this->controller->injectDb($db);

        Configuration::set('jtlconnector_from_date', '2024-01-01');

        self::assertSame([], $this->controller->pull(new QueryFilter()));
    }

    public function testOrdersAreReturnedAsMappedJtlInstances(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturnOnConsecutiveCalls([], [['id_order' => 5], ['id_order' => 7]]);
        $this->controller->injectDb($db);

        Configuration::set('jtlconnector_from_date', '');

        $order1 = (new JtlCustomerOrder())->setId(new Identity('5'));
        $order2 = (new JtlCustomerOrder())->setId(new Identity('7'));
        $this->controller->setMockCreatedOrders([$order1, $order2]);

        $result = $this->controller->pull(new QueryFilter());

        self::assertCount(2, $result);
        self::assertInstanceOf(JtlCustomerOrder::class, $result[0]);
        self::assertInstanceOf(JtlCustomerOrder::class, $result[1]);
        self::assertSame('5', $result[0]->getId()->getEndpoint());
        self::assertSame('7', $result[1]->getId()->getEndpoint());
    }

    public function testWithFromDateAndOrdersReturnsMappedInstances(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturnOnConsecutiveCalls([['Field' => 'deleted']], [['id_order' => 3]]);
        $this->controller->injectDb($db);

        Configuration::set('jtlconnector_from_date', '2024-06-01');

        $order1 = (new JtlCustomerOrder())->setId(new Identity('3'));
        $this->controller->setMockCreatedOrders([$order1]);

        $result = $this->controller->pull(new QueryFilter());

        self::assertCount(1, $result);
        self::assertSame('3', $result[0]->getId()->getEndpoint());
    }
}
