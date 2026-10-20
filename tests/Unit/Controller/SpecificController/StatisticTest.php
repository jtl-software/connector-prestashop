<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\SpecificController;

use Db;
use PHPUnit\Framework\TestCase;
use Tests\Support\Controller\TestableSpecificController;

final class StatisticTest extends TestCase
{
    private TestableSpecificController $controller;

    protected function setUp(): void
    {
        $this->controller = new TestableSpecificController();
    }

    public function testStatisticReturnsAvailableCount(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('getValue')->willReturn('3');
        $this->controller->injectDb($db);

        $result = $this->controller->statistic();

        self::assertSame(3, $result->getAvailable());
        self::assertSame('SpecificController', $result->getControllerName());
    }
}
