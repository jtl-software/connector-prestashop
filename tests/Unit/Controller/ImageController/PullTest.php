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
use Jtl\Connector\Core\Model\QueryFilter;
use PHPUnit\Framework\TestCase;
use Product;
use Tests\Support\Controller\TestableImageController;

final class PullTest extends TestCase
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

    public function testPullWithEmptyDbResultsReturnsEmptyArray(): void
    {
        $this->controller->injectDb($this->makeDbMock([]));

        $result = $this->controller->pull(new QueryFilter());

        self::assertSame([], $result);
    }

    public function testPullWithProductImageInDbReturnsProductImageInArray(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('escape')->willReturnArgument(0);
        $db->method('executeS')->willReturnOnConsecutiveCalls(
            // getProductImages: product rows from DB
            [['id_image' => '1', 'id_product' => '10', 'position' => '1']],
            // getPrestaImageI18n for that image
            [['id_lang' => 1, 'altText' => 'Alt']],
            // getCategoryImages: no results
            [],
            // getManufacturerImages: no results
            []
        );
        $this->controller->injectDb($db);

        $result = $this->controller->pull(new QueryFilter());

        self::assertCount(1, $result);
        self::assertInstanceOf(ProductImage::class, $result[0]);
    }

    public function testPullWithCategoryImageInDbReturnsCategoryImageInArray(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('escape')->willReturnArgument(0);
        $db->method('executeS')->willReturnOnConsecutiveCalls(
            // getProductImages: no results
            [],
            // getCategoryImages via getNotLinkedEntities
            [['id_category' => '5']],
            // getPrestaImageI18n for category image
            [['id_lang' => 1, 'altText' => 'CatAlt']],
            // getManufacturerImages: no results
            []
        );
        $this->controller->injectDb($db);

        $result = $this->controller->pull(new QueryFilter());

        self::assertCount(1, $result);
        self::assertInstanceOf(CategoryImage::class, $result[0]);
    }

    public function testPullWithManufacturerImageInDbReturnsManufacturerImageInArray(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('escape')->willReturnArgument(0);
        $db->method('executeS')->willReturnOnConsecutiveCalls(
            // getProductImages: no results
            [],
            // getCategoryImages: no results
            [],
            // getManufacturerImages via getNotLinkedEntities
            [['id_manufacturer' => '7']],
            // getPrestaImageI18n for manufacturer image
            [['id_lang' => 1, 'altText' => 'ManuAlt']]
        );
        $this->controller->injectDb($db);

        $result = $this->controller->pull(new QueryFilter());

        self::assertCount(1, $result);
        self::assertInstanceOf(ManufacturerImage::class, $result[0]);
    }

    public function testPullMergesAllThreeImageTypesIntoSingleArray(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('escape')->willReturnArgument(0);
        $db->method('executeS')->willReturnOnConsecutiveCalls(
            [['id_image' => '1', 'id_product' => '10', 'position' => '1']],
            [['id_lang' => 1, 'altText' => 'PAlt']],
            [['id_category' => '5']],
            [['id_lang' => 1, 'altText' => 'CAlt']],
            [['id_manufacturer' => '7']],
            [['id_lang' => 1, 'altText' => 'MAlt']]
        );
        $this->controller->injectDb($db);

        $result = $this->controller->pull(new QueryFilter());

        self::assertCount(3, $result);
    }
}
