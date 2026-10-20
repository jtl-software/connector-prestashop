<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\ManufacturerController;

use Db;
use Jtl\Connector\Core\Model\ManufacturerI18n as JtlManufacturerI18n;
use PHPUnit\Framework\TestCase;
use Tests\Support\Controller\TestableManufacturerController;

final class CreatePrestaManufacturerTranslationsTest extends TestCase
{
    private TestableManufacturerController $controller;

    protected function setUp(): void
    {
        Db::resetInstance();
        $this->controller = new TestableManufacturerController();
    }

    protected function tearDown(): void
    {
        Db::resetInstance();
    }

    public function testCreatePrestaManufacturerTranslationsWithSingleI18nProducesCorrectArray(): void
    {
        $i18n = (new JtlManufacturerI18n())
            ->setLanguageIso('eng')
            ->setDescription('Manufacturer description')
            ->setTitleTag('Page title')
            ->setMetaKeywords('keyword1,keyword2')
            ->setMetaDescription('Meta desc');

        $result = $this->controller->exposeCreatePrestaManufacturerTranslations($i18n);

        // 'eng' → langId 1
        self::assertArrayHasKey(1, $result);
        self::assertSame('Manufacturer description', $result[1]['description']);
        self::assertSame('Page title', $result[1]['meta_title']);
        self::assertSame('keyword1,keyword2', $result[1]['meta_keywords']);
        self::assertSame('Meta desc', $result[1]['meta_description']);
    }

    public function testCreatePrestaManufacturerTranslationsWithTwoI18nProducesTwoEntries(): void
    {
        $engI18n = (new JtlManufacturerI18n())
            ->setLanguageIso('eng')
            ->setDescription('English description')
            ->setTitleTag('EN title')
            ->setMetaKeywords('en-kw')
            ->setMetaDescription('EN meta');

        $gerI18n = (new JtlManufacturerI18n())
            ->setLanguageIso('ger')
            ->setDescription('Deutsche Beschreibung')
            ->setTitleTag('DE Titel')
            ->setMetaKeywords('de-kw')
            ->setMetaDescription('DE meta');

        $result = $this->controller->exposeCreatePrestaManufacturerTranslations($engI18n, $gerI18n);

        self::assertCount(2, $result);
        self::assertArrayHasKey(1, $result); // eng → 1
        self::assertArrayHasKey(2, $result); // ger → 2
        self::assertSame('English description', $result[1]['description']);
        self::assertSame('Deutsche Beschreibung', $result[2]['description']);
        self::assertSame('EN title', $result[1]['meta_title']);
        self::assertSame('DE Titel', $result[2]['meta_title']);
    }

    public function testCreatePrestaManufacturerTranslationsReturnsEmptyArrayForNoI18ns(): void
    {
        $result = $this->controller->exposeCreatePrestaManufacturerTranslations();

        self::assertSame([], $result);
    }

    public function testCreatePrestaManufacturerTranslationsUsesEmptyStringsForUnsetFields(): void
    {
        $i18n = new JtlManufacturerI18n();
        $i18n->setLanguageIso('eng');

        $result = $this->controller->exposeCreatePrestaManufacturerTranslations($i18n);

        self::assertSame('', $result[1]['description']);
        self::assertSame('', $result[1]['meta_title']);
        self::assertSame('', $result[1]['meta_keywords']);
        self::assertSame('', $result[1]['meta_description']);
    }
}
