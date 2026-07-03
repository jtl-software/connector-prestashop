<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\ManufacturerController;

use Db;
use Jtl\Connector\Core\Model\Manufacturer as JtlManufacturer;
use Jtl\Connector\Core\Model\ManufacturerI18n as JtlManufacturerI18n;
use Manufacturer;
use PHPUnit\Framework\TestCase;
use Tests\Support\Controller\TestableManufacturerController;

final class CreatePrestaManufacturerTest extends TestCase
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

    private function buildJtlManufacturer(string $name, JtlManufacturerI18n ...$i18ns): JtlManufacturer
    {
        return (new JtlManufacturer())
            ->setName($name)
            ->setI18ns(...$i18ns);
    }

    private function buildI18n(string $iso, string $desc, string $title, string $kw, string $meta): JtlManufacturerI18n
    {
        return (new JtlManufacturerI18n())
            ->setLanguageIso($iso)
            ->setDescription($desc)
            ->setTitleTag($title)
            ->setMetaKeywords($kw)
            ->setMetaDescription($meta);
    }

    public function testCreatePrestaManufacturerSetsActiveToTrue(): void
    {
        $jtlManufacturer = $this->buildJtlManufacturer(
            'Acme Corp',
            $this->buildI18n('eng', 'Description', 'Title', 'kw', 'meta')
        );

        $result = $this->controller->exposeCreatePrestaManufacturer($jtlManufacturer, new Manufacturer());

        self::assertTrue($result->active);
    }

    public function testCreatePrestaManufacturerSetsNameFromJtlModel(): void
    {
        $jtlManufacturer = $this->buildJtlManufacturer(
            'Acme Corp',
            $this->buildI18n('eng', 'desc', 'title', 'kw', 'meta')
        );

        $result = $this->controller->exposeCreatePrestaManufacturer($jtlManufacturer, new Manufacturer());

        self::assertSame('Acme Corp', $result->name);
    }

    public function testCreatePrestaManufacturerFillsTranslationArrays(): void
    {
        $jtlManufacturer = $this->buildJtlManufacturer(
            'Acme Corp',
            $this->buildI18n('eng', 'English desc', 'EN title', 'en-kw', 'EN meta')
        );

        $result = $this->controller->exposeCreatePrestaManufacturer($jtlManufacturer, new Manufacturer());

        // langId 1 for 'eng'
        self::assertSame('English desc', $result->description[1]);
        self::assertSame('EN title', $result->meta_title[1]);
        self::assertSame('en-kw', $result->meta_keywords[1]);
        self::assertSame('EN meta', $result->meta_description[1]);
    }

    public function testCreatePrestaManufacturerWithMultipleI18nsFillsAllTranslations(): void
    {
        $jtlManufacturer = $this->buildJtlManufacturer(
            'Global Brand',
            $this->buildI18n('eng', 'EN desc', 'EN title', 'en-kw', 'EN meta'),
            $this->buildI18n('ger', 'DE desc', 'DE title', 'de-kw', 'DE meta')
        );

        $result = $this->controller->exposeCreatePrestaManufacturer($jtlManufacturer, new Manufacturer());

        // The loop sets name on every iteration; the final value is still the manufacturer name
        self::assertSame('Global Brand', $result->name);
        self::assertSame('EN desc', $result->description[1]);
        self::assertSame('DE desc', $result->description[2]);
        self::assertSame('EN title', $result->meta_title[1]);
        self::assertSame('DE title', $result->meta_title[2]);
    }
}
