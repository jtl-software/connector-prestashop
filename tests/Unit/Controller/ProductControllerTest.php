<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\Product as JtlProduct;
use Jtl\Connector\Core\Model\TranslatableAttribute;
use jtl\Connector\Presta\Controller\AbstractController;
use jtl\Connector\Presta\Controller\ProductController;
use jtl\Connector\Presta\Mapper\PrimaryKeyMapper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ProductControllerTest extends TestCase
{
    private \Db&MockObject $db;
    private PrimaryKeyMapper&MockObject $mapper;
    private ProductController&MockObject $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db     = $this->createMock(\Db::class);
        $this->mapper = $this->createMock(PrimaryKeyMapper::class);

        $this->controller = $this->getMockBuilder(ProductController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPrestaContextShopId'])
            ->getMock();

        $this->controller->method('getPrestaContextShopId')->willReturn(1);

        $dbProp = new \ReflectionProperty(AbstractController::class, 'db');
        $dbProp->setValue($this->controller, $this->db);

        $mapperProp = new \ReflectionProperty(AbstractController::class, 'mapper');
        $mapperProp->setValue($this->controller, $this->mapper);
    }

    public function testDeleteReturnsPassedModel(): void
    {
        $model = $this->createModelWithEndpoint('');

        $result = $this->controller->delete($model);

        self::assertSame($model, $result);
    }

    public function testMainCategoryIdAttributeIsCreatedAsInteger(): void
    {
        $controller = $this->createSpecialAttributesController();

        $product                      = new \Product();
        $product->id_category_default = 5;
        $product->online_only         = false;

        $attributes = $this->invokeCreateJtlSpecialAttributes($controller, $product);

        self::assertCount(1, $attributes);
        self::assertSame('main_category_id', $attributes[0]->getId()->getEndpoint());
        self::assertSame(TranslatableAttribute::TYPE_INT, $attributes[0]->getType());
        self::assertSame(5, $attributes[0]->getI18ns()[0]->getValue(TranslatableAttribute::TYPE_INT));
    }

    public function testMainCategoryIdAttributeIsOmittedWithoutDefaultCategory(): void
    {
        $controller = $this->createSpecialAttributesController();

        $product                      = new \Product();
        $product->id_category_default = 0;
        $product->online_only         = false;

        self::assertSame([], $this->invokeCreateJtlSpecialAttributes($controller, $product));
    }

    private function createSpecialAttributesController(): ProductController&MockObject
    {
        $controller = $this->getMockBuilder(ProductController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPrestaContextLanguageId', 'getJtlLanguageIsoFromLanguageId', 'getPrestaCarriers'])
            ->getMock();

        $controller->method('getPrestaContextLanguageId')->willReturn(1);
        $controller->method('getJtlLanguageIsoFromLanguageId')->willReturn('ger');
        $controller->method('getPrestaCarriers')->willReturn([]);

        return $controller;
    }

    /**
     * @return array<\Jtl\Connector\Core\Model\ProductAttribute>
     */
    private function invokeCreateJtlSpecialAttributes(ProductController $controller, \Product $product): array
    {
        $method = new \ReflectionMethod(ProductController::class, 'createJtlSpecialAttributes');

        /** @var array<\Jtl\Connector\Core\Model\ProductAttribute> */
        return $method->invoke($controller, $product);
    }

    private function createModelWithEndpoint(string $endpoint): JtlProduct
    {
        $identity = $this->createMock(Identity::class);
        $identity->method('getEndpoint')->willReturn($endpoint);

        $model = $this->createMock(JtlProduct::class);
        $model->method('getId')->willReturn($identity);

        return $model;
    }
}
