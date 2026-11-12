<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\ImageController;

use Configuration;
use Db;
use Hook;
use Image;
use ImageType;
use Jtl\Connector\Core\Definition\IdentityType;
use Jtl\Connector\Core\Model\CategoryImage;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\ManufacturerImage;
use Jtl\Connector\Core\Model\ProductImage;
use jtl\Connector\Presta\Mapper\PrimaryKeyMapper;
use ObjectModel;
use PHPUnit\Framework\TestCase;
use Product;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\Support\AfterHook;
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
        ObjectModel::resetObjectModelMock();

        $this->controller = new TestableImageController();

        @mkdir(_PS_PRODUCT_IMG_DIR_ . '1/', 0777, true);
        @touch(_PS_PRODUCT_IMG_DIR_ . '1/1.jpg');
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
        ObjectModel::resetObjectModelMock();
    }

    private function makeProductImage(string $endpoint = '', string $foreignKey = '', int $sort = 1): ProductImage
    {
        return (new ProductImage())
            ->setId(new Identity($endpoint, 7001))
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
        [$result] = $this->controller->push($image);

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
        [$result] = $this->controller->push($image);

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
        [$result] = $this->controller->push($image);

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
        [$result] = $this->controller->push($image);

        self::assertSame($image, $result);
    }

    public function testNewProductImageIsLinkedOnceWithItsPrestaShopId(): void
    {
        Configuration::set('PS_HIGHT_DPI', '0');

        $mapper = $this->createMock(PrimaryKeyMapper::class);
        $mapper->expects(self::once())->method('save')->with(IdentityType::PRODUCT_IMAGE, '42', 7001);
        $this->controller->injectMapper($mapper);
        $this->controller->injectDb($this->makeDbMock());

        [$result] = $this->controller->push($this->makeProductImage('', '10', 2));

        self::assertSame('42', $result->getId()->getEndpoint());
    }

    public function testNewProductImageIsLinkedWhenPsEventbusReadsTheStackInItsHook(): void
    {
        Configuration::set('PS_HIGHT_DPI', '0');
        ObjectModel::$mockAfterHook = AfterHook::psEventbusErrorHandler();

        $mapper = $this->createMock(PrimaryKeyMapper::class);
        $mapper->expects(self::once())->method('save')->with(IdentityType::PRODUCT_IMAGE, '42', 7001);
        $this->controller->injectMapper($mapper);
        $this->controller->injectDb($this->makeDbMock());

        [$result] = $this->controller->push($this->makeProductImage('', '10', 2));

        self::assertSame('42', $result->getId()->getEndpoint());
    }

    public function testNewProductImageIsLinkedAndAWarningLoggedWhenAHookThrowsAfterTheInsert(): void
    {
        Configuration::set('PS_HIGHT_DPI', '0');
        ObjectModel::$mockAfterHook = AfterHook::throwing(new \TypeError('ps_eventbus failed'));

        $mapper = $this->createMock(PrimaryKeyMapper::class);
        $mapper->expects(self::once())->method('save')->with(IdentityType::PRODUCT_IMAGE, '42', 7001);
        $this->controller->injectMapper($mapper);
        $this->controller->injectDb($this->makeDbMock());
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(self::stringContains('Image 42'), self::anything());
        $this->controller->injectLogger($logger);

        [$result] = $this->controller->push($this->makeProductImage('', '10', 2));

        self::assertSame('42', $result->getId()->getEndpoint());
    }

    public function testNewProductImageStaysLinkedWhenALaterStepFails(): void
    {
        // the link is saved right after the image's INSERT, so the next sync updates the image instead of adding it
        Configuration::set('PS_HIGHT_DPI', '0');

        $mapper = $this->createMock(PrimaryKeyMapper::class);
        $mapper->expects(self::once())->method('save')->with(IdentityType::PRODUCT_IMAGE, '42', 7001);
        $this->controller->injectMapper($mapper);
        $db    = $this->createMock(Db::class);
        $error = new \PrestaShopDatabaseException('product_attribute_image failed');
        $db->method('execute')->willThrowException($error);
        $this->controller->injectDb($db);

        $this->expectExceptionObject($error);

        $this->controller->push($this->makeProductImage('', '10_5', 2));
    }

    public function testProductImageDeletedInTheShopIsAddedAndLinkedInsteadOfItsStaleLink(): void
    {
        Configuration::set('PS_HIGHT_DPI', '0');
        Image::$mockMissingIds = [57];

        $mapper = $this->createMock(PrimaryKeyMapper::class);
        $mapper->expects(self::once())->method('delete')->with(IdentityType::PRODUCT_IMAGE, '57', 7001);
        $mapper->expects(self::once())->method('save')->with(IdentityType::PRODUCT_IMAGE, '42', 7001);
        $this->controller->injectMapper($mapper);
        $this->controller->injectDb($this->makeDbMock());

        [$result] = $this->controller->push($this->makeProductImage('57', '10', 2));

        self::assertSame('42', $result->getId()->getEndpoint());
    }

    public function testUpdatingAProductImageFailsWhenPrestaShopCannotUpdateIt(): void
    {
        Configuration::set('PS_HIGHT_DPI', '0');
        Image::$mockUpdateResult = false;

        $this->controller->injectMapper($this->createMock(PrimaryKeyMapper::class));
        $this->controller->injectDb($this->makeDbMock());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error updating product image 42');

        $this->controller->push($this->makeProductImage('42', '10', 2));
    }

    public function testNewProductImageIsNotLinkedWhenAddFails(): void
    {
        Configuration::set('PS_HIGHT_DPI', '0');
        Image::$mockAddResult = false;

        $mapper = $this->createMock(PrimaryKeyMapper::class);
        $mapper->expects(self::never())->method('save');
        $this->controller->injectMapper($mapper);
        $this->controller->injectDb($this->makeDbMock());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error adding product image');

        $this->controller->push($this->makeProductImage('', '10', 2));
    }

    public function testUpdatesAndDeletesProductImagesWithoutStackFramesThatPsEventbusCannotRead(): void
    {
        Configuration::set('PS_HIGHT_DPI', '0');
        ObjectModel::$mockAfterHook = AfterHook::psEventbusErrorHandler();

        $mapper = $this->createMock(PrimaryKeyMapper::class);
        $this->controller->injectMapper($mapper);
        $this->controller->injectDb($this->makeDbMock());
        $image = $this->makeProductImage('42', '10', 2);

        self::assertSame([$image], $this->controller->push($image));
        self::assertSame([$image], $this->controller->delete($image));
    }
}
