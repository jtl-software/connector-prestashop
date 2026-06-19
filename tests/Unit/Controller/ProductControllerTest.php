<?php

declare(strict_types=1);

namespace jtl\Connector\Presta\Tests\Unit\Controller;

use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\Product as JtlProduct;
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

    public function testDeleteReturnsEmptyArrayForNoModels(): void
    {
        $result = $this->controller->delete();

        self::assertSame([], $result);
    }

    public function testDeleteReturnsAllPassedModels(): void
    {
        $model = $this->createModelWithEndpoint('');

        $result = $this->controller->delete($model);

        self::assertCount(1, $result);
        self::assertSame($model, $result[0]);
    }

    public function testDeleteHandlesMultipleModels(): void
    {
        $model1 = $this->createModelWithEndpoint('');
        $model2 = $this->createModelWithEndpoint('');

        $result = $this->controller->delete($model1, $model2);

        self::assertCount(2, $result);
        self::assertSame($model1, $result[0]);
        self::assertSame($model2, $result[1]);
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
