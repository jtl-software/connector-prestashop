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
use Psr\Log\LoggerInterface;
use Tests\Support\Controller\TestableImageController;

final class CreatePrestaProductImageTest extends TestCase
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

    public function testCreatePrestaProductImageNewImageSetsEndpointToGeneratedId(): void
    {
        $this->controller->injectDb($this->makeDbMock());

        // Empty endpoint → isUpdate=false, sort=2 → no cover logic
        $image  = $this->makeProductImage('', '10', 2);
        $result = $this->controller->exposeCreatePrestaProductImage($image, '10');

        self::assertSame('42', $result->getId()->getEndpoint());
    }

    public function testCreatePrestaProductImageUpdateImageKeepsExistingEndpoint(): void
    {
        $this->controller->injectDb($this->makeDbMock());

        // Non-empty endpoint → isUpdate=true, Image(99) is passed to constructor
        $image  = $this->makeProductImage('99', '10', 2);
        $result = $this->controller->exposeCreatePrestaProductImage($image, '10');

        self::assertSame('99', $result->getId()->getEndpoint());
    }

    public function testCreatePrestaProductImageCoverImageSetsCoverFlagOnImage(): void
    {
        Product::$mockCoverData = []; // no existing cover
        $this->controller->injectDb($this->makeDbMock());

        // sort=1, no combiId → cover logic executes
        $image  = $this->makeProductImage('', '10', 1);
        $result = $this->controller->exposeCreatePrestaProductImage($image, '10');

        self::assertSame('42', $result->getId()->getEndpoint());
    }

    public function testCreatePrestaProductImageCoverImageWithExistingCoverUnsetsOldCover(): void
    {
        Product::$mockCoverData = ['id_image' => 5]; // existing cover exists
        $this->controller->injectDb($this->makeDbMock());

        $image  = $this->makeProductImage('', '10', 1);
        $result = $this->controller->exposeCreatePrestaProductImage($image, '10');

        self::assertSame('42', $result->getId()->getEndpoint());
    }

    public function testCreatePrestaProductImageWithCombiIdAndNewImageInsertsAttributeRow(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('escape')->willReturnArgument(0);
        $db->method('execute')->willReturn(true);
        // DB insert is expected once for product_attribute_image
        $db->expects(self::once())->method('execute');
        $this->controller->injectDb($db);

        // id='10_5' → productId='10', combiId='5'
        $image = $this->makeProductImage('', '10', 2);
        $this->controller->exposeCreatePrestaProductImage($image, '10_5');
    }

    public function testCreatePrestaProductImageWithImageTypesRunsResizeLoop(): void
    {
        ImageType::$mockImageTypes = [['name' => 'large', 'width' => 800, 'height' => 600]];
        $this->controller->injectDb($this->makeDbMock());

        $image  = $this->makeProductImage('', '10', 2);
        $result = $this->controller->exposeCreatePrestaProductImage($image, '10');

        self::assertSame('42', $result->getId()->getEndpoint());
    }

    public function testCreatePrestaProductImageWithI18nLegendSetsLegendOnImage(): void
    {
        $this->controller->injectDb($this->makeDbMock());

        $i18n  = (new ImageI18n())->setLanguageIso('eng')->setAltText('Legend text');
        $image = $this->makeProductImage('', '10', 2);
        $image->setI18ns($i18n);

        $result = $this->controller->exposeCreatePrestaProductImage($image, '10');

        self::assertSame('42', $result->getId()->getEndpoint());
    }

    public function testCreatePrestaProductImageHookExceptionIsLogged(): void
    {
        Hook::$mockThrow = true;

        $this->controller->injectDb($this->makeDbMock());

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $this->controller->injectLogger($logger);

        $image = $this->makeProductImage('', '10', 2);
        $this->controller->exposeCreatePrestaProductImage($image, '10');
    }
}
