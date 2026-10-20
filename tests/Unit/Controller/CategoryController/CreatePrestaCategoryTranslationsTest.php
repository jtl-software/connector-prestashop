<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\CategoryController;

use Configuration;
use Jtl\Connector\Core\Model\CategoryI18n as JtlCategoryI18n;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Controller\TestableCategoryController;
use Tools;

/**
 * createPrestaCategoryTranslations: maps JtlCategoryI18n objects to the
 * PrestaShop-native translation array keyed by language ID.
 *
 * Covers: field mapping, name sanitisation, URL resolution,
 * description truncation, multi-language input, empty input.
 */
final class CreatePrestaCategoryTranslationsTest extends TestCase
{
    private TestableCategoryController $controller;

    protected function setUp(): void
    {
        Configuration::resetAll();
        Tools::resetMock();
        $this->controller = new TestableCategoryController();
    }

    protected function tearDown(): void
    {
        Configuration::resetAll();
        Tools::resetMock();
    }

    private function engI18n(): JtlCategoryI18n
    {
        return (new JtlCategoryI18n())->setLanguageIso('eng');
    }

    public function testProducesCorrectArrayStructureForSingleI18n(): void
    {
        $i18n = $this->engI18n()
            ->setName('Schuhe')
            ->setDescription('Alle Schuhe')
            ->setMetaDescription('Meta Schuhe')
            ->setMetaKeywords('Schuhe,Stiefel');

        $result = $this->controller->exposeCreatePrestaCategoryTranslations($i18n);

        self::assertArrayHasKey(1, $result);
        self::assertSame('Schuhe', $result[1]['name']);
        self::assertSame('Alle Schuhe', $result[1]['description']);
        self::assertSame('Meta Schuhe', $result[1]['metaDescription']);
        self::assertSame('Schuhe,Stiefel', $result[1]['metaKeywords']);
        self::assertArrayHasKey('url', $result[1]);
    }

    public function testSanitizesSpecialCharsInName(): void
    {
        $result = $this->controller->exposeCreatePrestaCategoryTranslations(
            $this->engI18n()->setName('Cat<>re;=#{}Name')
        );

        self::assertSame('Cat__re_____Name', $result[1]['name']);
    }

    public function testUsesConvertedNameAsUrlWhenUrlPathIsEmpty(): void
    {
        $result = $this->controller->exposeCreatePrestaCategoryTranslations(
            $this->engI18n()->setName('Mein Produkt')->setUrlPath('')
        );

        self::assertSame('mein-produkt', $result[1]['url']);
    }

    public function testUsesUrlPathDirectlyWhenSet(): void
    {
        $result = $this->controller->exposeCreatePrestaCategoryTranslations(
            $this->engI18n()->setName('Mein Produkt')->setUrlPath('custom-url-path')
        );

        self::assertSame('custom-url-path', $result[1]['url']);
    }

    public function testTruncatesDescriptionAndMetaDescriptionWhenConfigEnabled(): void
    {
        Configuration::set('jtlconnector_truncate_desc', true);

        $result = $this->controller->exposeCreatePrestaCategoryTranslations(
            $this->engI18n()
                ->setName('Test')
                ->setDescription(\str_repeat('a', 30000))
                ->setMetaDescription(\str_repeat('b', 1000))
        );

        self::assertSame(21844, \mb_strlen($result[1]['description']));
        self::assertSame(512, \mb_strlen($result[1]['metaDescription']));
    }

    public function testDoesNotTruncateWhenConfigDisabled(): void
    {
        Configuration::set('jtlconnector_truncate_desc', false);

        $result = $this->controller->exposeCreatePrestaCategoryTranslations(
            $this->engI18n()
                ->setName('Test')
                ->setDescription(\str_repeat('a', 30000))
                ->setMetaDescription(\str_repeat('b', 1000))
        );

        self::assertSame(30000, \mb_strlen($result[1]['description']));
        self::assertSame(1000, \mb_strlen($result[1]['metaDescription']));
    }

    public function testDoesNotTruncateWhenConfigNotSet(): void
    {
        $result = $this->controller->exposeCreatePrestaCategoryTranslations(
            $this->engI18n()
                ->setName('Test')
                ->setDescription(\str_repeat('x', 25000))
                ->setMetaDescription(\str_repeat('y', 600))
        );

        self::assertSame(25000, \mb_strlen($result[1]['description']));
        self::assertSame(600, \mb_strlen($result[1]['metaDescription']));
    }

    public function testHandlesMultipleI18nsKeyedByLanguageId(): void
    {
        $result = $this->controller->exposeCreatePrestaCategoryTranslations(
            $this->engI18n()->setName('Shoes')->setDescription('English description'),
            (new JtlCategoryI18n())->setLanguageIso('ger')->setName('Schuhe')->setDescription('Deutsche Beschreibung')
        );

        self::assertCount(2, $result);
        self::assertSame('Shoes', $result[1]['name']);
        self::assertSame('Schuhe', $result[2]['name']);
    }

    public function testReturnsEmptyArrayWhenNoI18nsGiven(): void
    {
        self::assertSame([], $this->controller->exposeCreatePrestaCategoryTranslations());
    }

    public function testThrowsWhenStr2urlReturnsNull(): void
    {
        Tools::$mockStr2urlNull = true;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Url must be a string');

        $this->controller->exposeCreatePrestaCategoryTranslations(
            $this->engI18n()->setName('TestName')
        );
    }
}
