<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\CustomerController;

use Db;
use Jtl\Connector\Core\Model\Customer as JtlCustomer;
use Jtl\Connector\Core\Model\Identity;
use PHPUnit\Framework\TestCase;
use Tests\Support\Controller\TestableCustomerController;

final class StatisticAndDeleteTest extends TestCase
{
    private TestableCustomerController $controller;

    protected function setUp(): void
    {
        $this->controller = new TestableCustomerController();
    }

    public function testStatisticReturnsAvailableCount(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('getValue')->willReturn('3');
        $this->controller->injectDb($db);

        $result = $this->controller->statistic();

        self::assertSame(3, $result->getAvailable());
        self::assertSame('CustomerController', $result->getControllerName());
    }

    public function testDeleteReturnsModel(): void
    {
        $jtlCustomer = (new JtlCustomer())
            ->setId(new Identity('5'));

        $result = $this->controller->delete($jtlCustomer);
        self::assertSame($jtlCustomer, $result);
    }
}
