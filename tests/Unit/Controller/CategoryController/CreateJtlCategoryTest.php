<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\CategoryController;

use Category;
use Jtl\Connector\Core\Model\Category as JtlCategory;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Controller\TestableCategoryControllerFull;

/**
 * createJtlCategory: maps one raw DB row to a JtlCategory.
 * Validates numeric fields, resolves the root-category boundary,
 * and delegates translation loading to createJtlCategoryTranslations (stubbed).
 */
final class CreateJtlCategoryTest extends TestCase
{
    protected function setUp(): void
    {
        Category::resetMock();
    }

    protected function tearDown(): void
    {
        Category::resetMock();
    }

    private function validRow(array $overrides = []): array
    {
        return array_merge([
            'active'      => '1',
            'level_depth' => '2',
            'id_category' => '5',
            'id_parent'   => '10',
        ], $overrides);
    }

    public function testMapsValidRowToJtlCategory(): void
    {
        $controller = new TestableCategoryControllerFull();

        $result = $controller->exposeCreateJtlCategory($this->validRow());

        self::assertInstanceOf(JtlCategory::class, $result);
        self::assertSame('5', $result->getId()->getEndpoint());
        self::assertTrue($result->getIsActive());
        self::assertSame(2, $result->getLevel());
        self::assertSame('10', $result->getParentCategoryId()->getEndpoint());
    }

    public function testSetsParentToEmptyWhenParentMatchesRootCategory(): void
    {
        Category::$mockRootCategoryId = 1;
        $controller = new TestableCategoryControllerFull();

        $result = $controller->exposeCreateJtlCategory($this->validRow(['id_parent' => '1']));

        self::assertSame('', $result->getParentCategoryId()->getEndpoint());
    }

    public function testSetsParentToEmptyWhenParentIsTwo(): void
    {
        $controller = new TestableCategoryControllerFull();

        $result = $controller->exposeCreateJtlCategory($this->validRow(['id_parent' => '2']));

        self::assertSame('', $result->getParentCategoryId()->getEndpoint());
    }

    public function testSetsParentEndpointWhenParentIsNonRoot(): void
    {
        $controller = new TestableCategoryControllerFull();

        $result = $controller->exposeCreateJtlCategory($this->validRow(['id_parent' => '10']));

        self::assertSame('10', $result->getParentCategoryId()->getEndpoint());
    }

    public function testThrowsWhenRootCategoryIdIsNull(): void
    {
        Category::$mockRootCategoryIdNull = true;
        $controller = new TestableCategoryControllerFull();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Root category id not found');

        $controller->exposeCreateJtlCategory($this->validRow());
    }

    public function testThrowsWhenActiveIsNotNumeric(): void
    {
        $controller = new TestableCategoryControllerFull();

        $this->expectException(RuntimeException::class);

        $controller->exposeCreateJtlCategory($this->validRow(['active' => 'x']));
    }

    public function testThrowsWhenLevelDepthIsNotNumeric(): void
    {
        $controller = new TestableCategoryControllerFull();

        $this->expectException(RuntimeException::class);

        $controller->exposeCreateJtlCategory($this->validRow(['level_depth' => 'x']));
    }

    public function testThrowsWhenIdCategoryIsNotNumeric(): void
    {
        $controller = new TestableCategoryControllerFull();

        $this->expectException(RuntimeException::class);

        $controller->exposeCreateJtlCategory($this->validRow(['id_category' => 'x']));
    }

    public function testThrowsWhenIdParentIsNotNumeric(): void
    {
        $controller = new TestableCategoryControllerFull();

        $this->expectException(RuntimeException::class);

        $controller->exposeCreateJtlCategory($this->validRow(['id_parent' => 'x']));
    }
}
