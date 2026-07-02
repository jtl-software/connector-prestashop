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
use jtl\Connector\Presta\Mapper\PrimaryKeyMapper;
use PHPUnit\Framework\TestCase;
use Product;
use Tests\Support\Controller\TestableImageController;

final class PushTest extends TestCase
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

    private function makeDbMock(array $executeS = [], bool $executeResult = true): Db
    {
        $db = $this->createMock(Db::class);
        $db->method('escape')->willReturnArgument(0);
        $db->method('executeS')->willReturn($executeS);
        $db->method('execute')->willReturn($executeResult);
        return $db;
    }

    public function testPushWithEmptyForeignKeyReturnsModelUnchanged(): void
    {
        $image  = $this->makeProductImage('', '', 1); // fId empty
        $result = $this->controller->push($image);

        self::assertSame($image, $result);
    }

    public function testPushCategoryImageCallsMapperSaveWithCategoryImageIdentityType(): void
    {
        Configuration::set('PS_HIGHT_DPI', '0');

        $mapper = $this->createMock(PrimaryKeyMapper::class);
        $mapper->expects(self::once())->method('save');
        $this->controller->injectMapper($mapper);
        $this->controller->injectDb($this->makeDbMock());

        $image  = $this->makeCategoryImage('', '5');
        $result = $this->controller->push($image);

        self::assertSame($image, $result);
    }

    public function testPushManufacturerImageCallsMapperSave(): void
    {
        Configuration::set('PS_HIGHT_DPI', '0');

        $mapper = $this->createMock(PrimaryKeyMapper::class);
        $mapper->expects(self::once())->method('save');
        $this->controller->injectMapper($mapper);
        $this->controller->injectDb($this->makeDbMock());

        $image  = $this->makeManufacturerImage('', '7');
        $result = $this->controller->push($image);

        self::assertSame($image, $result);
    }

    public function testPushProductImageCallsMapperSave(): void
    {
        Configuration::set('PS_HIGHT_DPI', '0');
        Product::$mockCoverData = [];

        $mapper = $this->createMock(PrimaryKeyMapper::class);
        $mapper->expects(self::once())->method('save');
        $this->controller->injectMapper($mapper);
        $this->controller->injectDb($this->makeDbMock());

        $image  = $this->makeProductImage('', '10', 2);
        $result = $this->controller->push($image);

        self::assertSame($image, $result);
    }
}
