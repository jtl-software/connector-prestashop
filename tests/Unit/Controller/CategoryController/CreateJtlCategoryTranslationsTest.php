<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\CategoryController;

use Db;
use Jtl\Connector\Core\Model\CategoryI18n as JtlCategoryI18n;
use PHPUnit\Framework\TestCase;
use Tests\Support\Controller\TestableCategoryController;

/**
 * createJtlCategoryTranslations: queries the DB for all language rows of one
 * category and returns a mapped JtlCategoryI18n per row.
 */
final class CreateJtlCategoryTranslationsTest extends TestCase
{
    private TestableCategoryController $controller;

    protected function setUp(): void
    {
        $this->controller = new TestableCategoryController();
    }

    public function testReturnsEmptyArrayWhenDbReturnsEmptyResult(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturn([]);
        $this->controller->injectDb($db);

        self::assertSame([], $this->controller->exposeCreateJtlCategoryTranslations(5));
    }

    public function testReturnsEmptyArrayWhenDbReturnsFalse(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturn(false);
        $this->controller->injectDb($db);

        self::assertSame([], $this->controller->exposeCreateJtlCategoryTranslations(5));
    }

    public function testReturnsMappedI18nForSingleRow(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturn([
            [
                'id_lang'          => '1',
                'name'             => 'Electronics',
                'description'      => 'All electronics',
                'meta_title'       => 'Electronics Title',
                'meta_keywords'    => 'electronics,gadgets',
                'meta_description' => 'Meta for electronics',
                'link_rewrite'     => 'electronics',
            ],
        ]);
        $this->controller->injectDb($db);

        $result = $this->controller->exposeCreateJtlCategoryTranslations(5);

        self::assertCount(1, $result);
        self::assertInstanceOf(JtlCategoryI18n::class, $result[0]);
        self::assertSame('Electronics', $result[0]->getName());
        self::assertSame('Electronics Title', $result[0]->getTitleTag());
        self::assertSame('All electronics', $result[0]->getDescription());
        self::assertSame('Meta for electronics', $result[0]->getMetaDescription());
        self::assertSame('electronics,gadgets', $result[0]->getMetaKeywords());
        self::assertSame('eng', $result[0]->getLanguageIso());
    }

    public function testReturnsMappedI18nsForMultipleRows(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturn([
            [
                'id_lang'          => '1',
                'name'             => 'Shoes',
                'description'      => 'All shoes',
                'meta_title'       => '',
                'meta_keywords'    => '',
                'meta_description' => '',
            ],
            [
                'id_lang'          => '2',
                'name'             => 'Schuhe',
                'description'      => 'Alle Schuhe',
                'meta_title'       => '',
                'meta_keywords'    => '',
                'meta_description' => '',
            ],
        ]);
        $this->controller->injectDb($db);

        $result = $this->controller->exposeCreateJtlCategoryTranslations(7);

        self::assertCount(2, $result);
        self::assertSame('eng', $result[0]->getLanguageIso());
        self::assertSame('Shoes', $result[0]->getName());
        self::assertSame('ger', $result[1]->getLanguageIso());
        self::assertSame('Schuhe', $result[1]->getName());
    }
}
