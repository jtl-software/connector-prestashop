<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\ManufacturerController;

use Db;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\Manufacturer as JtlManufacturer;
use PHPUnit\Framework\TestCase;
use Tests\Support\Controller\TestableManufacturerController;

final class StatisticAndDeleteTest extends TestCase
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

    public function testStatisticReturnsAvailableCount(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('getValue')->willReturn('5');
        $this->controller->injectDb($db);

        $result = $this->controller->statistic();

        self::assertSame(5, $result->getAvailable());
        self::assertSame('ManufacturerController', $result->getControllerName());
    }

    public function testDeleteReturnsModel(): void
    {
        $jtlManufacturer = (new JtlManufacturer())
            ->setId(new Identity('42'));

        $result = $this->controller->delete($jtlManufacturer);

        self::assertSame($jtlManufacturer, $result);
    }
}
