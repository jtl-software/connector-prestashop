<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\CustomerOrder;

use Configuration;
use Db;
use Jtl\Connector\Core\Model\QueryFilter;
use RuntimeException;

/**
 * Covers statistic() — counting unlinked orders available for pull.
 *
 * Branches:
 *   - With / without the soft-delete `deleted` column in ps_orders.
 *   - With / without `jtlconnector_from_date` limiting the query window.
 *   - DB error path (executeS returns false → RuntimeException).
 */
final class CustomerOrderStatisticTest extends CustomerOrderControllerTestCase
{
    public function testReturnsAvailableCountFromDb(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturn([]);
        $db->method('getValue')->willReturn('5');
        $this->controller->injectDb($db);

        $result = $this->controller->statistic();

        self::assertSame(5, $result->getAvailable());
        self::assertSame('CustomerOrderController', $result->getControllerName());
    }

    public function testWithDeletedColumnStillReturnsCorrectCount(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturn([['Field' => 'deleted']]);
        $db->method('getValue')->willReturn('3');
        $this->controller->injectDb($db);

        self::assertSame(3, $this->controller->statistic()->getAvailable());
    }

    public function testThrowsWhenColumnQueryReturnsFalse(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturn(false);
        $this->controller->injectDb($db);

        $this->expectException(RuntimeException::class);
        $this->controller->statistic();
    }

    public function testWithFromDateConfiguredReturnsFilteredCount(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturn([]);
        $db->method('getValue')->willReturn('12');
        $this->controller->injectDb($db);

        Configuration::set('jtlconnector_from_date', '2024-01-01');

        $result = $this->controller->statistic();

        self::assertSame(12, $result->getAvailable());
        self::assertSame('CustomerOrderController', $result->getControllerName());
    }

    public function testWithFromDateAndDeletedColumnReturnsFilteredCount(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturn([['Field' => 'deleted']]);
        $db->method('getValue')->willReturn('4');
        $this->controller->injectDb($db);

        Configuration::set('jtlconnector_from_date', '2024-06-01');

        self::assertSame(4, $this->controller->statistic()->getAvailable());
    }
}
