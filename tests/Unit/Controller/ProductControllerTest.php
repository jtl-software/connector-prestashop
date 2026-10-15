<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

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

        \Product::resetMock();
        \Combination::resetMock();
    }

    protected function tearDown(): void
    {
        \Product::resetMock();
        \Combination::resetMock();

        parent::tearDown();
    }

    public function testDeleteReturnsPassedModel(): void
    {
        $model = $this->createModelWithEndpoint('');

        $result = $this->controller->delete($model);

        self::assertSame($model, $result);
    }

    /**
     * Regression test for an inverted condition that deleted the whole base
     * product when a variant/combination endpoint was passed, and silently
     * did nothing when a plain product endpoint was passed.
     */
    public function testDeleteRemovesCombinationWhenEndpointReferencesAVariant(): void
    {
        $model = $this->createModelWithEndpoint('42_7');

        $this->controller->delete($model);

        self::assertSame([7], \Combination::$deletedIds, 'the variant must be deleted');
        self::assertSame([], \Product::$deletedIds, 'the base product must not be touched');
    }

    public function testDeleteRemovesProductWhenEndpointHasNoVariant(): void
    {
        $model = $this->createModelWithEndpoint('42');

        $this->controller->delete($model);

        self::assertSame([42], \Product::$deletedIds, 'the base product must be deleted');
        self::assertSame([], \Combination::$deletedIds, 'no combination must be touched');
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
