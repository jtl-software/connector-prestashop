<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\ManufacturerController;

use Language;
use Manufacturer;
use PHPUnit\Framework\TestCase;
use Tests\Support\Controller\TestableManufacturerControllerWithRealI18ns;

final class CreateJtlManufacturerI18nsTest extends TestCase
{
    protected function setUp(): void
    {
        Language::resetMock();
    }

    protected function tearDown(): void
    {
        Language::resetMock();
    }

    public function testCreateJtlManufacturerI18nsWithEmptyLanguagesReturnsEmptyArray(): void
    {
        Language::$mockLanguagesList = [];

        $controller = new TestableManufacturerControllerWithRealI18ns();
        $result     = $controller->exposeCreateJtlManufacturerI18ns(new Manufacturer(1));

        self::assertSame([], $result);
    }

    public function testCreateJtlManufacturerI18nsWithOneLanguage(): void
    {
        Language::$mockLanguagesList = [['id_lang' => 1]];

        $manufacturer                      = new Manufacturer(1);
        $manufacturer->description[1]      = 'Desc';
        $manufacturer->meta_description[1] = 'MetaDesc';
        $manufacturer->meta_keywords[1]    = 'kw';
        $manufacturer->meta_title[1]       = 'Title';

        $controller = new TestableManufacturerControllerWithRealI18ns();
        $result     = $controller->exposeCreateJtlManufacturerI18ns($manufacturer);

        self::assertCount(1, $result);
        self::assertSame('eng', $result[0]->getLanguageIso());
        self::assertSame('Desc', $result[0]->getDescription());
    }
}
