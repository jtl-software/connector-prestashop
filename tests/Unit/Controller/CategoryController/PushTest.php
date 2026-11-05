<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\CategoryController;

use Category;
use Jtl\Connector\Core\Model\Category as JtlCategory;
use Jtl\Connector\Core\Model\CategoryI18n as JtlCategoryI18n;
use Jtl\Connector\Core\Definition\IdentityType;
use Jtl\Connector\Core\Model\Identity;
use jtl\Connector\Presta\Mapper\PrimaryKeyMapper;
use ObjectModel;
use PHPUnit\Exception as PHPUnitException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\Support\AfterHook;
use Tests\Support\Controller\TestableCategoryController;

/**
 * push: creates or updates a PrestaShop category from a JtlCategory.
 *
 * Covers: new-category insert with mapper.save, duplicate-endpoint cleanup
 * (mapper.delete + mapper.save), existing-category update via endpoint,
 * root categories keeping their PrestaShop parent, parent-endpoint remapping
 * via mapper, and failure paths for add/update and a missing root category.
 */
final class PushTest extends TestCase
{
    private TestableCategoryController $controller;

    protected function setUp(): void
    {
        Category::resetMock();
        ObjectModel::resetObjectModelMock();
        // as PrestaShop's INSERT does
        ObjectModel::$mockNextInsertId = 501;
        $this->controller              = new TestableCategoryController();
    }

    protected function tearDown(): void
    {
        Category::resetMock();
        ObjectModel::resetObjectModelMock();
    }

    private function jtlCategory(string $endpointId, JtlCategoryI18n ...$i18ns): JtlCategory
    {
        return (new JtlCategory())
            ->setId(new Identity($endpointId, 5001))
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

        self::assertSame([$jtlCategory], $this->controller->push($jtlCategory));
    }

    public function testNewCategoryDeletesExistingMappingBeforeSave(): void
    {
        $mapper = $this->createMock(PrimaryKeyMapper::class);
        // First call: parent lookup → null; second call: after add() → '77' (stale mapping found)
        $mapper->method('getEndpointId')->willReturnOnConsecutiveCalls(null, '77');
        $mapper->expects(self::once())->method('delete');
        $mapper->expects(self::once())->method('save');
        $this->controller->injectMapper($mapper);

        [$result] = $this->controller->push(
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

        [$result] = $this->controller->push(
            $this->jtlCategory('5', $this->engI18n('Existing Category', 'existing-category'))
        );

        self::assertInstanceOf(JtlCategory::class, $result);
        self::assertSame('3', $result->getParentCategoryId()->getEndpoint());
    }

    public function testExistingRootCategoryKeepsItsPrestaShopParent(): void
    {
        // As in PrestaShop: the shop root is Home (id 2), itself a child of the hidden "Root" category (id 1).
        // The distinct ids make sure the root branch keeps id_parent instead of using the shop root id.
        Category::$mockRootCategoryId       = 2;
        Category::$mockLoadedIsRootCategory = true;
        Category::$mockLoadedIdParent       = 1;

        $mapper = $this->createMock(PrimaryKeyMapper::class);
        $mapper->method('getEndpointId')->willReturn(null);
        $mapper->expects(self::never())->method('save');
        $this->controller->injectMapper($mapper);

        [$result] = $this->controller->push(
            $this->jtlCategory('2', $this->engI18n('Home', 'home'))
        );

        // The PrestaShop parent of a root category has no JTL counterpart, hence host 0.
        self::assertSame('1', $result->getParentCategoryId()->getEndpoint());
        self::assertSame(0, $result->getParentCategoryId()->getHost());
        self::assertNotNull(Category::$mockLastUpdated);
        self::assertSame(2, Category::$mockLastUpdated->id);
        self::assertSame(1, Category::$mockLastUpdated->id_parent);
    }

    public function testThrowsWhenPrestaShopRootCategoryIdIsMissing(): void
    {
        Category::$mockRootCategoryIdNull = true;

        $mapper = $this->createMock(PrimaryKeyMapper::class);
        $mapper->method('getEndpointId')->willReturn(null);
        $mapper->expects(self::never())->method('save');
        $this->controller->injectMapper($mapper);

        // Caught explicitly instead of expectException() so that the add() count can be asserted after the throw.
        $thrown = null;
        try {
            $this->controller->push(
                $this->jtlCategory('', $this->engI18n('Orphan Category', 'orphan-category'))
            );
        } catch (PHPUnitException $e) {
            // PHPUnit's own exceptions (e.g. a violated mock expectation) mostly extend RuntimeException: never swallow them
            throw $e;
        } catch (RuntimeException $e) {
            $thrown = $e;
        }

        self::assertInstanceOf(RuntimeException::class, $thrown, 'Expected a RuntimeException for the missing root category');
        self::assertSame('Root category id not found', $thrown->getMessage());
        // The category must be rejected before anything is written to PrestaShop or mapped.
        self::assertSame(0, Category::$mockAddCalls);
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

        [$result] = $this->controller->push(
            $this->jtlCategory('5', $this->engI18n('Category With Remapped Parent', 'remapped-parent'))
        );

        self::assertSame('99', $result->getParentCategoryId()->getEndpoint());
    }

    public function testNewCategoryIsLinkedWhenPsEventbusReadsTheStackInItsHook(): void
    {
        // CO-3735: ps_eventbus 4.1.1 throws a TypeError for a stack frame without a file, after the INSERT
        ObjectModel::$mockAfterHook = AfterHook::psEventbusErrorHandler();

        $mapper = $this->createMock(PrimaryKeyMapper::class);
        $mapper->method('getEndpointId')->willReturn(null);
        $mapper->expects(self::once())->method('save')->with(IdentityType::CATEGORY, '501', 5001);
        $this->controller->injectMapper($mapper);

        $jtlCategory = $this->jtlCategory('', $this->engI18n('Hooked Category', 'hooked-category'));

        self::assertSame([$jtlCategory], $this->controller->push($jtlCategory));
    }

    public function testNewCategoryIsLinkedAndAWarningLoggedWhenAHookThrowsAfterTheInsert(): void
    {
        ObjectModel::$mockAfterHook = AfterHook::throwing(new \TypeError('ps_eventbus failed'));

        $mapper = $this->createMock(PrimaryKeyMapper::class);
        $mapper->method('getEndpointId')->willReturn(null);
        $mapper->expects(self::once())->method('save')->with(IdentityType::CATEGORY, '501', 5001);
        $this->controller->injectMapper($mapper);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(self::stringContains('Category 501'), self::anything());
        $this->controller->injectLogger($logger);

        $jtlCategory = $this->jtlCategory('', $this->engI18n('Hooked Category', 'hooked-category'));

        self::assertSame([$jtlCategory], $this->controller->push($jtlCategory));
    }

    public function testNewCategoryIsNotLinkedWhenAddThrowsBeforeTheInsert(): void
    {
        ObjectModel::$mockNextInsertId = null; // the category gets no id
        $error                         = new RuntimeException('insert failed');
        ObjectModel::$mockAfterHook = AfterHook::throwing($error);

        $mapper = $this->createMock(PrimaryKeyMapper::class);
        $mapper->method('getEndpointId')->willReturn(null);
        $mapper->expects(self::never())->method('save');
        $this->controller->injectMapper($mapper);

        $this->expectExceptionObject($error);

        $this->controller->push($this->jtlCategory('', $this->engI18n('Failing Category', 'failing-category')));
    }

    public function testNewCategoryWithAnInvalidMultilangFieldIsNeitherAddedNorLinked(): void
    {
        // before PrestaShop 9.1, add() would validate it only after the INSERT
        $error                                = new \PrestaShopException('Property Category->meta_description is not valid');
        ObjectModel::$mockLangValidationError = $error;

        $mapper = $this->createMock(PrimaryKeyMapper::class);
        $mapper->method('getEndpointId')->willReturn(null);
        $mapper->expects(self::never())->method('save');
        $this->controller->injectMapper($mapper);

        try {
            $this->controller->push($this->jtlCategory('', $this->engI18n('Invalid Category', 'invalid-category')));
            self::fail('Expected the validation error');
        } catch (\PrestaShopException $e) {
            self::assertSame($error, $e);
        }

        self::assertSame(0, Category::$mockAddCalls);
    }

    public function testExistingCategoryReportsAnErrorThrownAfterItsUpdate(): void
    {
        // Updates are not tolerated like adds: PrestaShop doesn't tell whether the error came before or after the
        // UPDATE. The link exists anyway, so the next sync updates the category again instead of adding it.
        $error                      = new RuntimeException('hook failed');
        ObjectModel::$mockAfterHook = AfterHook::throwing($error, 'Update');

        $mapper = $this->createMock(PrimaryKeyMapper::class);
        $mapper->method('getEndpointId')->willReturn(null);
        $mapper->expects(self::never())->method('save');
        $mapper->expects(self::never())->method('delete');
        $this->controller->injectMapper($mapper);

        $this->expectExceptionObject($error);

        $this->controller->push($this->jtlCategory('5', $this->engI18n('Existing Category', 'existing-category')));
    }

    public function testUpdatesExistingCategoriesWithoutStackFramesThatPsEventbusCannotRead(): void
    {
        ObjectModel::$mockAfterHook = AfterHook::psEventbusErrorHandler();

        $mapper = $this->createMock(PrimaryKeyMapper::class);
        $mapper->method('getEndpointId')->willReturn(null);
        $this->controller->injectMapper($mapper);

        $jtlCategory = $this->jtlCategory('5', $this->engI18n('Existing Category', 'existing-category'));

        self::assertSame([$jtlCategory], $this->controller->push($jtlCategory));
    }
}
