<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use Jtl\Connector\Core\Model\CategoryI18n as JtlCategoryI18n;
use jtl\Connector\Presta\Controller\CategoryController;
use PHPUnit\Framework\TestCase;

final class CategoryControllerTest extends TestCase
{
    public function testCreateJtlCategoryTranslationUsesProvidedValues(): void
    {
        $controller = new CategoryControllerForTest();

        $translation = $controller->createTranslation([
            'id_category' => '10',
            'id_shop' => '1',
            'id_lang' => '2',
            'name' => 'Kategorie A',
            'description' => 'Beschreibung',
            'additional_description' => '',
            'link_rewrite' => 'kategorie-a',
            'meta_title' => 'Meta Titel',
            'meta_keywords' => 'eins,zwei',
            'meta_description' => 'Meta Beschreibung',
        ]);

        self::assertInstanceOf(JtlCategoryI18n::class, $translation);
        self::assertSame('Kategorie A', $translation->getName());
        self::assertSame('Meta Titel', $translation->getTitleTag());
        self::assertSame('Beschreibung', $translation->getDescription());
        self::assertSame('Meta Beschreibung', $translation->getMetaDescription());
        self::assertSame('eins,zwei', $translation->getMetaKeywords());
        self::assertSame('ger', $translation->getLanguageIso());
    }

    public function testCreateJtlCategoryTranslationUsesEmptyStringFallbacksForMissingMetaFields(): void
    {
        $controller = new CategoryControllerForTest();

        $translation = $controller->createTranslation([
            'id_category' => '11',
            'id_shop' => '1',
            'id_lang' => '1',
            'name' => 'Kategorie B',
        ]);

        self::assertSame('Kategorie B', $translation->getName());
        self::assertSame('', $translation->getTitleTag());
        self::assertSame('', $translation->getDescription());
        self::assertSame('', $translation->getMetaDescription());
        self::assertSame('', $translation->getMetaKeywords());
        self::assertSame('eng', $translation->getLanguageIso());
    }
}

final class CategoryControllerForTest extends CategoryController
{
    public function __construct()
    {
    }

    /**
     * @param array<string, string> $prestaCategoryI18n
     */
    public function createTranslation(array $prestaCategoryI18n): JtlCategoryI18n
    {
        return $this->createJtlCategoryTranslation($prestaCategoryI18n);
    }

    protected function getJtlLanguageIsoFromLanguageId(string|int $langId): string
    {
        return match ((string) $langId) {
            '2' => 'ger',
            default => 'eng',
        };
    }
}
