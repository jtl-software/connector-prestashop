<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\ManufacturerController;

use Db;
use Jtl\Connector\Core\Model\Manufacturer as JtlManufacturer;
use Jtl\Connector\Core\Model\QueryFilter;
use PHPUnit\Framework\TestCase;
use Tests\Support\Controller\TestableManufacturerController;

final class PullTest extends TestCase
{
    private TestableManufacturerController $controller;

    protected function setUp(): void
    {
        Db::resetInstance();
        $this->controller = new TestableManufacturerController();
    }

    protected function tearDown(): void
    {
        Db::resetInstance();
    }

    public function testPullWithEmptyResultReturnsEmptyArray(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturn([]);
        $this->controller->injectDb($db);

        $result = $this->controller->pull(new QueryFilter());

        self::assertSame([], $result);
    }

    public function testPullWithOneManufacturerReturnsMappedManufacturer(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturn([
            ['id_manufacturer' => '5'],
        ]);
        $this->controller->injectDb($db);
        $this->controller->stubI18ns();

        $result = $this->controller->pull(new QueryFilter());

        self::assertCount(1, $result);
        self::assertInstanceOf(JtlManufacturer::class, $result[0]);
        self::assertSame('5', $result[0]->getId()->getEndpoint());
    }

    public function testPullWithMultipleManufacturersReturnsMappedCollection(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturn([
            ['id_manufacturer' => '1'],
            ['id_manufacturer' => '2'],
            ['id_manufacturer' => '3'],
        ]);
        $this->controller->injectDb($db);
        $this->controller->stubI18ns();

        $result = $this->controller->pull(new QueryFilter());

        self::assertCount(3, $result);
    }
}
