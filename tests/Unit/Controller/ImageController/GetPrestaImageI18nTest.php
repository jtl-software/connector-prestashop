<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\ImageController;

use Configuration;
use Db;
use Hook;
use Image;
use ImageType;
use Jtl\Connector\Core\Model\CategoryImage;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\ManufacturerImage;
use Jtl\Connector\Core\Model\ProductImage;
use PHPUnit\Framework\TestCase;
use Product;
use RuntimeException;
use Tests\Support\Controller\TestableImageController;

final class GetPrestaImageI18nTest extends TestCase
{
    private TestableImageController $controller;

    protected function setUp(): void
    {
        Db::resetInstance();
        Image::resetMock();
        ImageType::resetMock();
        Hook::resetMock();
        Product::resetMock();
        Configuration::resetAll();

        $this->controller = new TestableImageController();

        @mkdir(_PS_PROD_IMG_DIR_ . '1/', 0777, true);
        @touch(_PS_PROD_IMG_DIR_ . '1/1.jpg');
        @mkdir(_PS_CAT_IMG_DIR_, 0777, true);
        @touch(_PS_CAT_IMG_DIR_ . '5.jpg');
        @mkdir(_PS_MANU_IMG_DIR_, 0777, true);
        @touch(_PS_MANU_IMG_DIR_ . '7.jpg');
        @mkdir(sys_get_temp_dir() . '/jtl_ps_test/path_for_creation/', 0777, true);
        @touch(sys_get_temp_dir() . '/jtl_ps_test/path_for_creation/42.jpg');
    }

    protected function tearDown(): void
    {
        Db::resetInstance();
        Image::resetMock();
        ImageType::resetMock();
        Hook::resetMock();
        Product::resetMock();
        Configuration::resetAll();
    }

    private function makeProductImage(string $endpoint = '', string $foreignKey = '', int $sort = 1): ProductImage
    {
        return (new ProductImage())
            ->setId(new Identity($endpoint))
            ->setForeignKey(new Identity($foreignKey))
            ->setSort($sort)
            ->setFilename('test.jpg');
    }

    private function makeCategoryImage(string $endpoint = '', string $foreignKey = ''): CategoryImage
    {
        return (new CategoryImage())
            ->setId(new Identity($endpoint))
            ->setForeignKey(new Identity($foreignKey))
            ->setFilename('test.jpg');
    }

    private function makeManufacturerImage(string $endpoint = '', string $foreignKey = ''): ManufacturerImage
    {
        return (new ManufacturerImage())
            ->setId(new Identity($endpoint))
            ->setForeignKey(new Identity($foreignKey))
            ->setFilename('test.jpg');
    }

    public function testGetPrestaImageI18nWithNumericIdUsesFullId(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturn([['id_lang' => 1, 'altText' => 'Alt text']]);
        $this->controller->injectDb($db);

        $result = $this->controller->exposeGetPrestaImageI18n($this->makeProductImage('42'));

        self::assertCount(1, $result);
        self::assertSame('Alt text', $result[0]['altText']);
    }

    public function testGetPrestaImageI18nWithPrefixedIdStripsFirstCharacter(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturn([['id_lang' => 1, 'altText' => 'Cat alt']]);
        $this->controller->injectDb($db);

        // Category image ID starts with 'c' (non-numeric) → first char stripped
        $result = $this->controller->exposeGetPrestaImageI18n($this->makeCategoryImage('c5'));

        self::assertCount(1, $result);
        self::assertSame('Cat alt', $result[0]['altText']);
    }

    public function testGetPrestaImageI18nWithManufacturerPrefixStripsFirstCharacter(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturn([['id_lang' => 1, 'altText' => 'Manu alt']]);
        $this->controller->injectDb($db);

        $result = $this->controller->exposeGetPrestaImageI18n($this->makeManufacturerImage('m7'));

        self::assertCount(1, $result);
        self::assertSame('Manu alt', $result[0]['altText']);
    }

    public function testGetPrestaImageI18nThrowsWhenDbReturnsFalse(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturn(false);
        $this->controller->injectDb($db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to fetch image i18n data');

        $this->controller->exposeGetPrestaImageI18n($this->makeProductImage('1'));
    }
}
