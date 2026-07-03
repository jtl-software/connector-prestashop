<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\CategoryController;

use Category;
use Jtl\Connector\Core\Model\Category as JtlCategory;
use Jtl\Connector\Core\Model\CategoryI18n as JtlCategoryI18n;
use Jtl\Connector\Core\Model\Identity;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Controller\TestableCategoryController;

/**
 * createPrestaCategory: populates a PrestaShop Category from a JtlCategory.
 *
 * Covers: active flag, parent-ID resolution (explicit vs. root fallback),
 * translation field mapping, and the null-root-category guard.
 */
final class CreatePrestaCategoryTest extends TestCase
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

    private function jtlCategory(bool $active, string $parentEndpoint, JtlCategoryI18n ...$i18ns): JtlCategory
    {
        return (new JtlCategory())
            ->setId(new Identity(''))
            ->setIsActive($active)
            ->setParentCategoryId(new Identity($parentEndpoint))
            ->setI18ns(...$i18ns);
    }

    private function engI18n(string $name, string $urlPath = 'slug'): JtlCategoryI18n
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

    public function testSetsActiveFlagFromJtlCategory(): void
    {
        $result = $this->controller->exposeCreatePrestaCategory(
            $this->jtlCategory(true, '3', $this->engI18n('Test Category')),
            new Category()
        );

        self::assertInstanceOf(Category::class, $result);
        self::assertTrue($result->active);
    }

    public function testSetsInactiveFlagFromJtlCategory(): void
    {
        $result = $this->controller->exposeCreatePrestaCategory(
            $this->jtlCategory(false, '3', $this->engI18n('Inactive Category')),
            new Category()
        );

        self::assertFalse($result->active);
    }

    public function testSetsParentIdFromNonEmptyEndpoint(): void
    {
        $result = $this->controller->exposeCreatePrestaCategory(
            $this->jtlCategory(true, '42', $this->engI18n('Child Category')),
            new Category()
        );

        self::assertSame(42, $result->id_parent);
    }

    public function testFallsBackToRootCategoryWhenParentEndpointIsEmpty(): void
    {
        Category::$mockRootCategoryId = 1;

        $result = $this->controller->exposeCreatePrestaCategory(
            $this->jtlCategory(true, '', $this->engI18n('Root Child')),
            new Category()
        );

        self::assertSame(1, $result->id_parent);
    }

    public function testThrowsWhenParentEndpointIsEmptyAndRootCategoryIdIsNull(): void
    {
        Category::$mockRootCategoryIdNull = true;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Root category id not found');

        $this->controller->exposeCreatePrestaCategory(
            $this->jtlCategory(true, '', $this->engI18n('Root Cat Null')),
            new Category()
        );
    }

    public function testSetsTranslationFieldsIndexedByLanguageId(): void
    {
        $i18n = (new JtlCategoryI18n())
            ->setName('Electronics')
            ->setDescription('Electronics description')
            ->setMetaDescription('Electronics meta')
            ->setMetaKeywords('electronics')
            ->setLanguageIso('eng')
            ->setUrlPath('electronics');

        $result = $this->controller->exposeCreatePrestaCategory(
            $this->jtlCategory(true, '3', $i18n),
            new Category()
        );

        // 'eng' → language ID 1 in the stub
        self::assertArrayHasKey(1, $result->name);
        self::assertSame('Electronics', $result->name[1]);
        self::assertSame('Electronics description', $result->description[1]);
        self::assertSame('Electronics meta', $result->meta_description[1]);
        self::assertSame('electronics', $result->meta_keywords[1]);
        self::assertSame('electronics', $result->link_rewrite[1]);
    }
}
