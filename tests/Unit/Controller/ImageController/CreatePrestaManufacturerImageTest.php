<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\ImageController;

use Configuration;
use Db;
use Hook;
use Image;
use ImageType;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\ManufacturerImage;
use PHPUnit\Framework\TestCase;
use Product;
use Tests\Support\Controller\TestableImageController;

final class CreatePrestaManufacturerImageTest extends TestCase
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

    private function makeManufacturerImage(string $endpoint = '', string $foreignKey = ''): ManufacturerImage
    {
        return (new ManufacturerImage())
            ->setId(new Identity($endpoint))
            ->setForeignKey(new Identity($foreignKey))
            ->setFilename('test.jpg');
    }

    public function testCreatePrestaManufacturerImageSetsEndpointWithMPrefix(): void
    {
        $image = $this->makeManufacturerImage('', '7');

        $result = $this->controller->exposeCreatePrestaManufacturerImage($image, false, '7');

        self::assertSame('m7', $result->getId()->getEndpoint());
    }

    public function testCreatePrestaManufacturerImageWithImageTypesRunsResizeLoop(): void
    {
        ImageType::$mockImageTypes = [['name' => 'thumb', 'width' => 200, 'height' => 200]];

        $image  = $this->makeManufacturerImage('', '7');
        $result = $this->controller->exposeCreatePrestaManufacturerImage($image, false, '7');

        self::assertSame('m7', $result->getId()->getEndpoint());
    }

    public function testCreatePrestaManufacturerImageWithHightDpiRunsExtraResizeCall(): void
    {
        ImageType::$mockImageTypes = [['name' => 'thumb', 'width' => 200, 'height' => 200]];

        $image  = $this->makeManufacturerImage('', '7');
        $result = $this->controller->exposeCreatePrestaManufacturerImage($image, true, '7');

        self::assertSame('m7', $result->getId()->getEndpoint());
    }
}
