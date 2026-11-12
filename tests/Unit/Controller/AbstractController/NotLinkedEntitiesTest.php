<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\AbstractController;

use Db;
use Jtl\Connector\Core\Model\QueryFilter;
use PHPUnit\Framework\TestCase;
use Tests\Support\Controller\AbstractControllerWithRealHelpers;

final class NotLinkedEntitiesTest extends TestCase
{
    private Db $db;

    protected function setUp(): void
    {
        $this->db = $this->createMock(Db::class);
    }

    private function makeController(): AbstractControllerWithRealHelpers
    {
        return new AbstractControllerWithRealHelpers($this->db);
    }

    private function makeQueryFilter(int $limit = 100): QueryFilter
    {
        $qf = new QueryFilter();
        $qf->setLimit($limit);
        return $qf;
    }

    public function testGetNotLinkedEntitiesReturnsResultRows(): void
    {
        $this->db->method('executeS')
            ->willReturn([['id_category' => '1']]);

        $result = $this->makeController()->exposeGetNotLinked(
            $this->makeQueryFilter(),
            'jtl_connector_link_category',
            'category',
            'id_category'
        );

        self::assertCount(1, $result);
        self::assertSame('1', $result[0]['id_category']);
    }

    public function testGetNotLinkedEntitiesWithFromDatePassesStrippedDate(): void
    {
        $rows = [['id_category' => '2']];
        $this->db->method('executeS')
            ->willReturn($rows);

        $result = $this->makeController()->exposeGetNotLinked(
            $this->makeQueryFilter(),
            'jtl_connector_link_category',
            'category',
            'id_category',
            '2023-01-01'
        );

        self::assertCount(1, $result);
    }

    public function testGetNotLinkedEntitiesCustomerOrderWithEmptyShowColumns(): void
    {
        // First call: SHOW COLUMNS returns empty → no deleted filter
        // Second call: the main SELECT returns data
        $this->db->method('executeS')
            ->willReturnOnConsecutiveCalls(
                [],
                [['id_order' => '5']]
            );

        $result = $this->makeController()->exposeGetNotLinked(
            $this->makeQueryFilter(),
            'jtl_connector_link_customer_order',
            'orders',
            'id_order'
        );

        self::assertCount(1, $result);
        self::assertSame('5', $result[0]['id_order']);
    }

    public function testGetNotLinkedEntitiesCustomerOrderWithDeletedColumn(): void
    {
        // First call: SHOW COLUMNS returns the deleted column → deleted=0 filter added
        // Second call: main SELECT
        $this->db->method('executeS')
            ->willReturnOnConsecutiveCalls(
                [['Field' => 'deleted']],
                [['id_order' => '7']]
            );

        $result = $this->makeController()->exposeGetNotLinked(
            $this->makeQueryFilter(),
            'jtl_connector_link_customer_order',
            'orders',
            'id_order'
        );

        self::assertCount(1, $result);
        self::assertSame('7', $result[0]['id_order']);
    }
}
