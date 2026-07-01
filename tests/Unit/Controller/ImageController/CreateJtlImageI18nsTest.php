<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\ImageController;

use Configuration;
use Db;
use Hook;
use Image;
use ImageType;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\ImageI18n;
use Jtl\Connector\Core\Model\ProductImage;
use PHPUnit\Framework\TestCase;
use Product;
use Tests\Support\Controller\TestableImageController;

final class CreateJtlImageI18nsTest extends TestCase
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

    private function makeDbMock(array $executeS = [], bool $executeResult = true): Db
    {
        $db = $this->createMock(Db::class);
        $db->method('escape')->willReturnArgument(0);
        $db->method('executeS')->willReturn($executeS);
        $db->method('execute')->willReturn($executeResult);
        return $db;
    }

    public function testCreateJtlImageI18nsWithEmptyDataReturnsEmptyArray(): void
    {
        $this->controller->injectDb($this->makeDbMock([]));

        $result = $this->controller->exposeCreateJtlImageI18ns($this->makeProductImage('1'));

        self::assertSame([], $result);
    }

    public function testCreateJtlImageI18nsWithDataReturnsPopulatedImageI18nArray(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturn([['id_lang' => 1, 'altText' => 'AltText']]);
        $this->controller->injectDb($db);

        $result = $this->controller->exposeCreateJtlImageI18ns($this->makeProductImage('1'));

        self::assertCount(1, $result);
        self::assertInstanceOf(ImageI18n::class, $result[0]);
        self::assertSame('AltText', $result[0]->getAltText());
        self::assertSame('eng', $result[0]->getLanguageIso());
    }
}
