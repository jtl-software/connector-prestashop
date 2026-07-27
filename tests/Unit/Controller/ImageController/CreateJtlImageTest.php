<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\ImageController;

use Configuration;
use Db;
use Hook;
use Image;
use ImageType;
use Jtl\Connector\Core\Model\CategoryImage;
use Jtl\Connector\Core\Model\ManufacturerImage;
use Jtl\Connector\Core\Model\ProductImage;
use PHPUnit\Framework\TestCase;
use Product;
use RuntimeException;
use Tests\Support\Controller\TestableImageController;

final class CreateJtlImageTest extends TestCase
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

    private function makeDbMock(array $executeS = [], bool $executeResult = true): Db
    {
        $db = $this->createMock(Db::class);
        $db->method('escape')->willReturnArgument(0);
        $db->method('executeS')->willReturn($executeS);
        $db->method('execute')->willReturn($executeResult);
        return $db;
    }

    public function testCreateJtlImageWithProductTypeReturnsProductImage(): void
    {
        $this->controller->injectDb($this->makeDbMock());

        $result = $this->controller->exposeCreateJtlImage([
            'id'           => '1',
            'foreignKey'   => '10',
            'remoteUrl'    => 'http://localhost/img/1.jpg',
            'filename'     => '1.jpg',
            'relationType' => 'product',
            'sort'         => 1,
        ]);

        self::assertInstanceOf(ProductImage::class, $result);
        self::assertSame('1', $result->getId()->getEndpoint());
        self::assertSame('10', $result->getForeignKey()->getEndpoint());
        self::assertSame('1.jpg', $result->getFilename());
    }

    public function testCreateJtlImageWithCategoryTypeReturnsCategoryImage(): void
    {
        $this->controller->injectDb($this->makeDbMock());

        $result = $this->controller->exposeCreateJtlImage([
            'id'           => 'c5',
            'foreignKey'   => '5',
            'remoteUrl'    => 'http://localhost/img/c/5.jpg',
            'filename'     => '5.jpg',
            'relationType' => 'category',
        ]);

        self::assertInstanceOf(CategoryImage::class, $result);
        self::assertSame('c5', $result->getId()->getEndpoint());
    }

    public function testCreateJtlImageWithManufacturerTypeReturnsManufacturerImage(): void
    {
        $this->controller->injectDb($this->makeDbMock());

        $result = $this->controller->exposeCreateJtlImage([
            'id'           => 'm7',
            'foreignKey'   => '7',
            'remoteUrl'    => 'http://localhost/img/m/7.jpg',
            'filename'     => '7.jpg',
            'relationType' => 'manufacturer',
        ]);

        self::assertInstanceOf(ManufacturerImage::class, $result);
        self::assertSame('m7', $result->getId()->getEndpoint());
    }

    public function testCreateJtlImageWithUnknownTypeThrowsRuntimeException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to set $jtlImage based on relationType');

        $this->controller->exposeCreateJtlImage([
            'id'           => 'x1',
            'foreignKey'   => '1',
            'remoteUrl'    => 'http://localhost/img/x/1.jpg',
            'filename'     => '1.jpg',
            'relationType' => 'unknown',
        ]);
    }
}
