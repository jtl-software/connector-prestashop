<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\Product as JtlProduct;
use Tests\Support\AfterHook;
use Psr\Log\NullLogger;
use ObjectModel;
use Jtl\Connector\Core\Model\ProductPriceItem as JtlProductPriceItem;
use Jtl\Connector\Core\Model\ProductPrice as JtlProductPrice;
use jtl\Connector\Presta\Controller\AbstractController;
use jtl\Connector\Presta\Controller\ProductPriceController;
use jtl\Connector\Presta\Mapper\PrimaryKeyMapper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ProductPriceControllerTest extends TestCase
{
    private \Db&MockObject $db;
    private PrimaryKeyMapper&MockObject $mapper;
    private ProductPriceController&MockObject $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db     = $this->createMock(\Db::class);
        $this->mapper = $this->createMock(PrimaryKeyMapper::class);

        $this->controller = $this->getMockBuilder(ProductPriceController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['handlePrices', 'getPrestaContextShopId'])
            ->getMock();

        $this->controller->method('getPrestaContextShopId')->willReturn(1);

        $dbProp = new \ReflectionProperty(AbstractController::class, 'db');
        $dbProp->setValue($this->controller, $this->db);

        $mapperProp = new \ReflectionProperty(AbstractController::class, 'mapper');
        $mapperProp->setValue($this->controller, $this->mapper);
    }

    public function testPushReturnsPassedModel(): void
    {
        $model = $this->createModelWithEndpoint('');

        [$result] = $this->controller->push($model);

        self::assertSame($model, $result);
    }

    public function testPushSkipsDbOperationsWhenEndpointIsEmpty(): void
    {
        $model = $this->createModelWithEndpoint('');

        $this->db->expects(self::never())->method('delete');
        $this->controller->expects(self::never())->method('handlePrices');

        $this->controller->push($model);
    }

    public function testPushDeletesGroupSpecificPricesBeforeHandlingNewPrices(): void
    {
        $model = $this->createModelWithEndpoint('42_0');

        $this->db
            ->expects(self::once())
            ->method('delete')
            ->with(
                'specific_price',
                'id_product = 42 AND id_product_attribute = 0 AND id_group > 0 AND id_shop = 1'
            );

        $this->controller->push($model);
    }

    public function testDeleteGroupSpecificPricesBuildsCorrectWhereClause(): void
    {
        $this->db
            ->expects(self::once())
            ->method('delete')
            ->with(
                'specific_price',
                'id_product = 42 AND id_product_attribute = 7 AND id_group > 0 AND id_shop = 1'
            );

        $method = new \ReflectionMethod(ProductPriceController::class, 'deleteGroupSpecificPrices');
        $method->invoke($this->controller, 42, 7);
    }

    public function testDeleteGroupSpecificPricesOnlyDeletesGroupPricesNotDefaultPrices(): void
    {
        $capturedWhere = null;
        $this->db
            ->expects(self::once())
            ->method('delete')
            ->willReturnCallback(
                function (string $table, string $where) use (&$capturedWhere): bool {
                    $capturedWhere = $where;
                    return true;
                }
            );

        $method = new \ReflectionMethod(ProductPriceController::class, 'deleteGroupSpecificPrices');
        $method->invoke($this->controller, 100, 0);

        self::assertIsString($capturedWhere);
        self::assertStringContainsString('id_group > 0', $capturedWhere);
    }

    private function createModelWithEndpoint(string $endpoint): JtlProduct
    {
        $identity = $this->createMock(Identity::class);
        $identity->method('getEndpoint')->willReturn($endpoint);

        $model = $this->createMock(JtlProduct::class);
        $model->method('getId')->willReturn($identity);
        $model->method('getPrices')->willReturn([]);

        return $model;
    }

    public function testPushSavesPricesWithoutStackFramesThatPsEventbusCannotRead(): void
    {
        // CO-3735: ps_eventbus hooks into SpecificPrice. A real subclass instead of the mock: PHPUnit calls stubbed
        // methods through internal functions itself.
        $controller = new class extends ProductPriceController {
            public function __construct()
            {
                $this->db             = new \Db();
                $this->logger         = new NullLogger();
                $this->controllerName = 'ProductPriceController';
            }

            protected function handlePrices(
                int $productId,
                int $combiId,
                int $groupId,
                JtlProductPriceItem ...$priceItems
            ): void {
                (new \SpecificPrice())->add();
            }

            protected function getPrestaContextShopId(): int
            {
                return 1;
            }
        };
        $product = (new JtlProduct())
            ->setId(new Identity('10'))
            ->setPrices(
                (new JtlProductPrice())->setCustomerGroupId(new Identity('3'))->setItems(new JtlProductPriceItem())
            );
        ObjectModel::$mockAfterHook = AfterHook::psEventbusErrorHandler();

        try {
            self::assertSame([$product], $controller->push($product));
        } finally {
            ObjectModel::resetObjectModelMock();
        }
    }
}
