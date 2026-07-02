<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\CategoryController;

use Category;
use Jtl\Connector\Core\Model\Category as JtlCategory;
use Jtl\Connector\Core\Model\CategoryI18n as JtlCategoryI18n;
use Jtl\Connector\Core\Model\Identity;
use jtl\Connector\Presta\Mapper\PrimaryKeyMapper;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Controller\TestableCategoryController;

/**
 * push: creates or updates a PrestaShop category from a JtlCategory.
 *
 * Covers: new-category insert with mapper.save, duplicate-endpoint cleanup
 * (mapper.delete + mapper.save), existing-category update via endpoint,
 * parent-endpoint remapping via mapper, and failure paths for add/update.
 */
final class PushTest extends TestCase
{
    private TestableCategoryController $controller;

    protected function setUp(): void
    {
        Category::resetMock();
        $this->controller = new TestableCategoryController();
    }

    protected function tearDown(): void
    {
        Category::resetMock();
    }

    private function jtlCategory(string $endpointId, JtlCategoryI18n ...$i18ns): JtlCategory
    {
        return (new JtlCategory())
            ->setId(new Identity($endpointId))
            ->setIsActive(true)
            ->setParentCategoryId(new Identity('3'))
            ->setI18ns(...$i18ns);
    }

    private function engI18n(string $name, string $urlPath): JtlCategoryI18n
    {
        return (new JtlCategoryI18n())
            ->setName($name)
            ->setLanguageIso('eng')
            ->setDescription('')
            ->setMetaDescription('')
            ->setMetaKeywords('')
            ->setTitleTag('')
            ->setUrlPath($urlPath);
    }

    public function testNewCategoryCallsMapperSaveAndReturnsModel(): void
    {
        $mapper = $this->createMock(PrimaryKeyMapper::class);
        $mapper->method('getEndpointId')->willReturn(null);
        $mapper->expects(self::once())->method('save');
        $this->controller->injectMapper($mapper);

        $jtlCategory = $this->jtlCategory('', $this->engI18n('New Category', 'new-category'));

        self::assertSame($jtlCategory, $this->controller->push($jtlCategory));
    }

    public function testNewCategoryDeletesExistingMappingBeforeSave(): void
    {
        $mapper = $this->createMock(PrimaryKeyMapper::class);
        // First call: parent lookup → null; second call: after add() → '77' (stale mapping found)
        $mapper->method('getEndpointId')->willReturnOnConsecutiveCalls(null, '77');
        $mapper->expects(self::once())->method('delete');
        $mapper->expects(self::once())->method('save');
        $this->controller->injectMapper($mapper);

        $result = $this->controller->push(
            $this->jtlCategory('', $this->engI18n('New Category With Existing Mapping', 'new-category-existing-mapping'))
        );

        self::assertInstanceOf(JtlCategory::class, $result);
    }

    public function testNewCategoryThrowsAndDeletesPartialRecordWhenAddFails(): void
    {
        Category::$mockAddShouldFail = true;
        Category::$mockAddFailId     = 99;

        $mapper = $this->createMock(PrimaryKeyMapper::class);
        $mapper->method('getEndpointId')->willReturn(null);
        $this->controller->injectMapper($mapper);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Error uploading category/');

        $this->controller->push(
            $this->jtlCategory('', $this->engI18n('Broken Category', 'broken-category'))
        );
    }

    public function testNewCategoryStillThrowsWhenAddFailsAndDeleteAlsoThrows(): void
    {
        Category::$mockAddShouldFail     = true;
        Category::$mockAddFailId         = 99;
        Category::$mockDeleteShouldThrow = true;

        $mapper = $this->createMock(PrimaryKeyMapper::class);
        $mapper->method('getEndpointId')->willReturn(null);
        $this->controller->injectMapper($mapper);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error uploading category');

        $this->controller->push(
            $this->jtlCategory('', $this->engI18n('Delete Throws', 'delete-throws'))
        );
    }

    public function testExistingCategoryCallsUpdateAndSkipsMapperSave(): void
    {
        $mapper = $this->createMock(PrimaryKeyMapper::class);
        $mapper->method('getEndpointId')->willReturn(null);
        $mapper->expects(self::never())->method('save');
        $this->controller->injectMapper($mapper);

        $result = $this->controller->push(
            $this->jtlCategory('5', $this->engI18n('Existing Category', 'existing-category'))
        );

        self::assertInstanceOf(JtlCategory::class, $result);
    }

    public function testExistingCategoryThrowsWhenUpdateFails(): void
    {
        Category::$mockUpdateResult = false;

        $mapper = $this->createMock(PrimaryKeyMapper::class);
        $mapper->method('getEndpointId')->willReturn(null);
        $this->controller->injectMapper($mapper);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error updating category');

        $this->controller->push(
            $this->jtlCategory('5', $this->engI18n('Failing Update', 'failing-update'))
        );
    }

    public function testRemapsParentEndpointWhenMapperReturnsNonMatchingId(): void
    {
        $mapper = $this->createMock(PrimaryKeyMapper::class);
        $mapper->method('getEndpointId')->willReturn('99');
        $this->controller->injectMapper($mapper);

        $result = $this->controller->push(
            $this->jtlCategory('5', $this->engI18n('Category With Remapped Parent', 'remapped-parent'))
        );

        self::assertSame('99', $result->getParentCategoryId()->getEndpoint());
    }
}
