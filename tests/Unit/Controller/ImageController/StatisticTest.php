<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\ImageController;

use Configuration;
use Db;
use Hook;
use Image;
use ImageType;
use PHPUnit\Framework\TestCase;
use Product;
use Tests\Support\Controller\TestableImageController;

final class StatisticTest extends TestCase
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

    public function testStatisticReturnsZeroCountWhenDbReturnsNoImages(): void
    {
        $this->controller->injectDb($this->makeDbMock([]));

        $result = $this->controller->statistic();

        self::assertSame(0, $result->getAvailable());
        self::assertSame('ImageController', $result->getControllerName());
    }

    public function testStatisticCountsAllReturnedImages(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('escape')->willReturnArgument(0);
        $db->method('executeS')->willReturnOnConsecutiveCalls(
            [['id_image' => '1', 'id_product' => '10', 'position' => '1']],
            [['id_lang' => 1, 'altText' => 'PAlt']],
            [],
            []
        );
        $this->controller->injectDb($db);

        $result = $this->controller->statistic();

        self::assertSame(1, $result->getAvailable());
    }
}
