<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\ProductController;

use Db;
use Jtl\Connector\Core\Definition\IdentityType;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\Product as JtlProduct;
use Jtl\Connector\Core\Model\ProductI18n as JtlProductI18n;
use jtl\Connector\Presta\Mapper\PrimaryKeyMapper;
use ObjectModel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Product;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Tests\Support\AfterHook;
use Tests\Support\Controller\PushableProductController;

/**
 * push: how new products and variants are added and linked, also when a module hooked into PrestaShop throws
 * (CO-3735).
 */
final class PushTest extends TestCase
{
    private PrimaryKeyMapper&MockObject $mapper;

    protected function setUp(): void
    {
        Product::resetMock();
        ObjectModel::resetObjectModelMock();
        Db::resetInstance();
        ObjectModel::$mockNextInsertId = 900;
        $this->mapper                  = $this->createMock(PrimaryKeyMapper::class);
    }

    protected function tearDown(): void
    {
        Product::resetMock();
        ObjectModel::resetObjectModelMock();
        Db::resetInstance();
    }

    /**
     * @param array<int, int> ...$combinationIds the product's combination ids for each lookup, in order
     */
    private function controller(?LoggerInterface $logger = null, array ...$combinationIds): PushableProductController
    {
        $lookups = 0;
        $db      = $this->createMock(Db::class);
        $db->method('executeS')->willReturnCallback(
            static function (mixed $sql) use (&$lookups, $combinationIds): array {
                if (!\is_string($sql) || !\str_contains($sql, 'product_attribute')) {
                    return [];
                }
                $ids = $combinationIds[$lookups++] ?? [];

                return \array_map(static fn(int $id): array => ['id_product_attribute' => (string)$id], $ids);
            }
        );

        return new PushableProductController($db, $this->mapper, $logger ?? new NullLogger());
    }

    private function jtlProduct(string $endpoint, string $masterProductId = ''): JtlProduct
    {
        return (new JtlProduct())
            ->setId(new Identity($endpoint, 8001))
            ->setMasterProductId(new Identity($masterProductId))
            ->setI18ns((new JtlProductI18n())->setName('Shirt')->setLanguageIso('ger'));
    }

    private function expectProductLink(string $endpoint): void
    {
        $this->mapper->expects(self::once())->method('delete')->with(IdentityType::PRODUCT, null, 8001);
        $this->mapper->expects(self::once())->method('save')->with(IdentityType::PRODUCT, $endpoint, 8001);
    }

    public function testNewProductIsLinkedWhenPsEventbusReadsTheStackInItsHook(): void
    {
        ObjectModel::$mockAfterHook = AfterHook::psEventbusErrorHandler();
        $this->expectProductLink('900');

        [$result] = $this->controller()->push($this->jtlProduct(''));

        self::assertSame('900', $result->getId()->getEndpoint());
    }

    public function testNewProductIsLinkedAndAWarningLoggedWhenAHookThrowsAfterTheInsert(): void
    {
        ObjectModel::$mockAfterHook = AfterHook::throwing(new \TypeError('ps_eventbus failed'));
        $this->expectProductLink('900');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(self::stringContains('Product 900'), self::anything());

        [$result] = $this->controller($logger)->push($this->jtlProduct(''));

        self::assertSame('900', $result->getId()->getEndpoint());
        self::assertSame([], Product::$deletedIds);
    }

    public function testNewProductIsNeitherLinkedNorDeletedWhenAddFails(): void
    {
        Product::$mockAddResult = false;
        $this->mapper->expects(self::never())->method('save');

        try {
            $this->controller()->push($this->jtlProduct(''));
            self::fail('Expected the push to fail');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('Error adding product', $e->getMessage());
        }

        // nothing was added, so there is nothing to clean up
        self::assertSame([], Product::$deletedIds);
    }

    public function testProductToRecreateIsNotDeletedWhenAddingItFails(): void
    {
        // the linked product 555 was deleted in the shop, so it is added again; that fails before anything is added
        Product::$mockMissingIds = [555];
        Product::$mockAddResult  = false;
        $this->mapper->expects(self::never())->method('save');

        try {
            $this->controller()->push($this->jtlProduct('555'));
            self::fail('Expected the push to fail');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('Error adding product', $e->getMessage());
        }

        self::assertSame([], Product::$deletedIds);
    }

    public function testNewProductIsRemovedWithItsLinkWhenALaterStepFails(): void
    {
        // linked right after the INSERT, but a push failing with an exception removes the product again
        $controller                          = $this->controller();
        $controller->failUpdatePrestaProduct = new RuntimeException('mapping failed');
        $deletedLinks                        = 0;
        $this->mapper->expects(self::once())->method('save')->with(IdentityType::PRODUCT, '900', 8001);
        $this->mapper->method('delete')->willReturnCallback(
            static function (int $type, ?string $endpointId, ?int $hostId) use (&$deletedLinks): bool {
                self::assertSame([IdentityType::PRODUCT, null, 8001], [$type, $endpointId, $hostId]);
                $deletedLinks++;
                return true;
            }
        );

        try {
            $controller->push($this->jtlProduct(''));
            self::fail('Expected the push to fail');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('mapping failed', $e->getMessage());
        }

        self::assertSame([900], Product::$deletedIds);
        // before saving the link (an old link of the host) and in the cleanup
        self::assertSame(2, $deletedLinks);
    }

    public function testNewVariantIsLinkedWhenPsEventbusReadsTheStackInItsHook(): void
    {
        Product::$mockAddAttributeResult = 77;
        ObjectModel::$mockAfterHook      = AfterHook::psEventbusErrorHandler();
        $this->expectProductLink('900_77');

        [$result] = $this->controller()->push($this->jtlProduct('', '900'));

        self::assertSame('900_77', $result->getId()->getEndpoint());
    }

    public function testNewVariantIsLinkedWhenAddingItsCombinationThrowsAfterTheInsert(): void
    {
        // addAttribute() adds the combination itself, so its id is looked up among the product's new combinations
        Product::$mockAddAttributeResult = 77;
        ObjectModel::$mockAfterHook      = AfterHook::throwing(new \TypeError('ps_eventbus failed'));
        $this->expectProductLink('900_77');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(self::stringContains('Combination 77'), self::anything());

        [$result] = $this->controller($logger, [5], [5, 77])->push($this->jtlProduct('', '900'));

        self::assertSame('900_77', $result->getId()->getEndpoint());
    }

    public function testNewVariantIsRemovedWithItsLinkWhenALaterStepFails(): void
    {
        // linked right after its combination was added, but a push failing with an exception removes it again
        Product::$mockAddAttributeResult = 77;
        // fails when the combination is completed, i.e. after the variant was linked
        ObjectModel::$mockAfterHook = AfterHook::throwing(new RuntimeException('combination failed'), 'Update');
        $deletedLinks               = 0;
        $this->mapper->expects(self::once())->method('save')->with(IdentityType::PRODUCT, '900_77', 8001);
        $this->mapper->method('delete')->willReturnCallback(
            static function (int $type, ?string $endpointId, ?int $hostId) use (&$deletedLinks): bool {
                self::assertSame([IdentityType::PRODUCT, null, 8001], [$type, $endpointId, $hostId]);
                $deletedLinks++;
                return true;
            }
        );

        try {
            $this->controller()->push($this->jtlProduct('', '900'));
            self::fail('Expected the push to fail');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('combination failed', $e->getMessage());
        }

        self::assertSame([77], Product::$deletedCombinationIds);
        // before saving the link (an old link of the host) and in the cleanup
        self::assertSame(2, $deletedLinks);
    }

    public function testNewVariantIsNotLinkedWhenAddingItsCombinationThrowsBeforeTheInsert(): void
    {
        $error                           = new \TypeError('combination failed');
        Product::$mockAddAttributeResult = 77;
        ObjectModel::$mockAfterHook      = AfterHook::throwing($error);
        $this->mapper->expects(self::never())->method('save');

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('combination failed');

        // no new combination after the call: nothing was added
        $this->controller(null, [5], [5])->push($this->jtlProduct('', '900'));
    }

    public function testExistingProductsAreUpdatedAndDeletedWithoutStackFramesThatPsEventbusCannotRead(): void
    {
        ObjectModel::$mockAfterHook = AfterHook::psEventbusErrorHandler();
        $this->mapper->expects(self::never())->method('save');
        $controller = $this->controller();
        $jtlProduct = $this->jtlProduct('900');

        self::assertSame([$jtlProduct], $controller->push($jtlProduct));
        self::assertSame([$jtlProduct], $controller->delete($jtlProduct));
    }
}
