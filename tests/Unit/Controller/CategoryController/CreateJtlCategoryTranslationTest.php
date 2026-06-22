<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\CategoryController;

use Jtl\Connector\Core\Model\CategoryI18n as JtlCategoryI18n;
use PHPUnit\Framework\TestCase;
use Tests\Support\Controller\TestableCategoryController;

/**
 * createJtlCategoryTranslation: maps one raw DB row to a JtlCategoryI18n.
 * Language ISO resolution is delegated to getJtlLanguageIsoFromLanguageId.
 */
final class CreateJtlCategoryTranslationTest extends TestCase
{
    private TestableCategoryController $controller;

    protected function setUp(): void
    {
        $this->controller = new TestableCategoryController();
    }

    public function testMapsAllFieldsFromRow(): void
    {
        $data = [
            'id_lang'          => '2',
            'name'             => 'Kategorie A',
            'meta_title'       => 'Meta Titel',
            'description'      => 'Beschreibung',
            'meta_description' => 'Meta Beschreibung',
            'meta_keywords'    => 'eins,zwei',
        ];

        $result = $this->controller->exposeCreateJtlCategoryTranslation($data);

        self::assertInstanceOf(JtlCategoryI18n::class, $result);
        self::assertSame('Kategorie A', $result->getName());
        self::assertSame('Meta Titel', $result->getTitleTag());
        self::assertSame('Beschreibung', $result->getDescription());
        self::assertSame('Meta Beschreibung', $result->getMetaDescription());
        self::assertSame('eins,zwei', $result->getMetaKeywords());
        self::assertSame('ger', $result->getLanguageIso());
    }

    public function testUsesEmptyStringsForMissingOptionalFields(): void
    {
        $data = [
            'id_lang' => '1',
            'name'    => 'Kategorie B',
        ];

        $result = $this->controller->exposeCreateJtlCategoryTranslation($data);

        self::assertSame('Kategorie B', $result->getName());
        self::assertSame('', $result->getTitleTag());
        self::assertSame('', $result->getDescription());
        self::assertSame('', $result->getMetaDescription());
        self::assertSame('', $result->getMetaKeywords());
        self::assertSame('eng', $result->getLanguageIso());
    }

    public function testFallsBackToDefaultIsoForUnknownLanguageId(): void
    {
        $data = ['id_lang' => '99', 'name' => 'Test'];

        $result = $this->controller->exposeCreateJtlCategoryTranslation($data);

        self::assertSame('eng', $result->getLanguageIso());
    }
}
