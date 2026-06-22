<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\CategoryController;

use Category;
use Db;
use Jtl\Connector\Core\Model\Category as JtlCategory;
use Jtl\Connector\Core\Model\CategoryI18n as JtlCategoryI18n;
use Jtl\Connector\Core\Model\QueryFilter;
use PHPUnit\Framework\TestCase;
use Tests\Support\Controller\TestableCategoryController;
use Tests\Support\Controller\TestableCategoryControllerFull;

/**
 * pull: queries the DB for unsynced categories, maps each row to JtlCategory.
 *
 * Uses TestableCategoryControllerFull so that createJtlCategoryTranslations
 * is stubbed and the full mapping path can be exercised without a second DB call.
 */
final class PullTest extends TestCase
{
    protected function setUp(): void
    {
        Category::resetMock();
    }

    protected function tearDown(): void
    {
        Category::resetMock();
    }

    private function mockDb(array|false $rows): Db
    {
        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturn($rows);
        $db->method('escape')->willReturnArgument(0);
        return $db;
    }

    public function testReturnsEmptyArrayWhenDbReturnsEmptyResult(): void
    {
        $controller = new TestableCategoryController();
        $controller->injectDb($this->mockDb([]));

        self::assertSame([], $controller->pull(new QueryFilter()));
    }

    public function testReturnsEmptyArrayWhenDbReturnsFalse(): void
    {
        $controller = new TestableCategoryController();
        $controller->injectDb($this->mockDb(false));

        self::assertSame([], $controller->pull(new QueryFilter()));
    }

    public function testReturnsMappedJtlCategoryForSingleRow(): void
    {
        $controller = new TestableCategoryControllerFull();
        $controller->stubI18ns(
            (new JtlCategoryI18n())->setName('Electronics')->setLanguageIso('eng')
        );
        $controller->injectDb($this->mockDb([
            ['active' => '1', 'level_depth' => '2', 'id_category' => '5', 'id_parent' => '10'],
        ]));

        $result = $controller->pull(new QueryFilter());

        self::assertCount(1, $result);
        self::assertInstanceOf(JtlCategory::class, $result[0]);
        self::assertSame('5', $result[0]->getId()->getEndpoint());
    }

    public function testReturnsMappedJtlCategoriesForMultipleRows(): void
    {
        $controller = new TestableCategoryControllerFull();
        $controller->stubI18ns();
        $controller->injectDb($this->mockDb([
            ['active' => '1', 'level_depth' => '1', 'id_category' => '3', 'id_parent' => '10'],
            ['active' => '0', 'level_depth' => '2', 'id_category' => '7', 'id_parent' => '3'],
        ]));

        $result = $controller->pull(new QueryFilter());

        self::assertCount(2, $result);
        self::assertSame('3', $result[0]->getId()->getEndpoint());
        self::assertSame('7', $result[1]->getId()->getEndpoint());
        self::assertTrue($result[0]->getIsActive());
        self::assertFalse($result[1]->getIsActive());
    }
}
