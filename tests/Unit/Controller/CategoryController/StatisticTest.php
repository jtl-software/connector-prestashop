<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\CategoryController;

use Db;
use PHPUnit\Framework\TestCase;
use Tests\Support\Controller\TestableCategoryController;

/**
 * statistic: returns the count of categories available for sync.
 */
final class StatisticTest extends TestCase
{
    public function testReturnsAvailableCountFromDb(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('getValue')->willReturn('8');

        $controller = new TestableCategoryController();
        $controller->injectDb($db);

        $result = $controller->statistic();

        self::assertSame(8, $result->getAvailable());
        self::assertSame('CategoryController', $result->getControllerName());
    }
}
