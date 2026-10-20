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
use PHPUnit\Framework\TestCase;
use Product;
use Tests\Support\Controller\TestableImageController;

final class CreatePrestaCategoryImageTest extends TestCase
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

    private function makeCategoryImage(string $endpoint = '', string $foreignKey = ''): CategoryImage
    {
        return (new CategoryImage())
            ->setId(new Identity($endpoint))
            ->setForeignKey(new Identity($foreignKey))
            ->setFilename('test.jpg');
    }

    public function testCreatePrestaCategoryImageSetsEndpointWithCPrefix(): void
    {
        $image = $this->makeCategoryImage('', '5');

        $result = $this->controller->exposeCreatePrestaCategoryImage($image, false, '5');

        self::assertSame('c5', $result->getId()->getEndpoint());
    }

    public function testCreatePrestaCategoryImageWithImageTypesRunsResizeLoop(): void
    {
        ImageType::$mockImageTypes = [['name' => 'large', 'width' => 800, 'height' => 600]];

        $image  = $this->makeCategoryImage('', '5');
        $result = $this->controller->exposeCreatePrestaCategoryImage($image, false, '5');

        self::assertSame('c5', $result->getId()->getEndpoint());
    }

    public function testCreatePrestaCategoryImageWithHightDpiRunsExtraResizeCall(): void
    {
        ImageType::$mockImageTypes = [['name' => 'large', 'width' => 800, 'height' => 600]];

        $image  = $this->makeCategoryImage('', '5');
        $result = $this->controller->exposeCreatePrestaCategoryImage($image, true, '5');

        self::assertSame('c5', $result->getId()->getEndpoint());
    }

    public function testCreatePrestaCategoryImageWhenFileDoesNotExistSkipsTypeLoop(): void
    {
        // Use an id for which no temp file was created
        $image  = $this->makeCategoryImage('', '999');
        $result = $this->controller->exposeCreatePrestaCategoryImage($image, false, '999');

        self::assertSame('c999', $result->getId()->getEndpoint());
    }
}
