<?php

declare(strict_types=1);

namespace Tests\Unit\Mapper;

use Db;
use Jtl\Connector\Core\Definition\IdentityType;
use jtl\Connector\Presta\Mapper\PrimaryKeyMapper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

// ---------------------------------------------------------------------------
// Testable subclass that accepts an injected Db mock
// ---------------------------------------------------------------------------

final class TestablePrimaryKeyMapper extends PrimaryKeyMapper
{
    public function __construct(Db $db)
    {
        $this->db     = $db;
        $this->logger = new NullLogger();
    }
}

// ---------------------------------------------------------------------------
// Test class
// ---------------------------------------------------------------------------

final class PrimaryKeyMapperTest extends TestCase
{
    // =========================================================================
    // Part 0: __construct() – exercises the real constructor
    // =========================================================================

    public function testConstructorCreatesInstanceUsingDbSingleton(): void
    {
        // The Db stub's getInstance() returns a stub Db — no real DB connection needed.
        \Db::resetInstance();
        $mapper = new PrimaryKeyMapper();
        self::assertInstanceOf(PrimaryKeyMapper::class, $mapper);
    }

    // =========================================================================
    // Part 1: getTableName() – pure static, no DB required
    // =========================================================================

    public function testGetTableNameReturnsCategoryTableForCategoryType(): void
    {
        self::assertSame(
            'jtl_connector_link_category',
            PrimaryKeyMapper::getTableName(IdentityType::CATEGORY)
        );
    }

    public function testGetTableNameReturnsCrossSellingTableForCrossSellingType(): void
    {
        self::assertSame(
            'jtl_connector_link_crossselling',
            PrimaryKeyMapper::getTableName(IdentityType::CROSS_SELLING)
        );
    }

    public function testGetTableNameReturnsCrossSellingGroupTableForCrossSellingGroupType(): void
    {
        self::assertSame(
            'jtl_connector_link_crossselling_group',
            PrimaryKeyMapper::getTableName(IdentityType::CROSS_SELLING_GROUP)
        );
    }

    public function testGetTableNameReturnsCustomerTableForCustomerType(): void
    {
        self::assertSame(
            'jtl_connector_link_customer',
            PrimaryKeyMapper::getTableName(IdentityType::CUSTOMER)
        );
    }

    public function testGetTableNameReturnsCustomerTableForCustomerGroupType(): void
    {
        self::assertSame(
            'jtl_connector_link_customer',
            PrimaryKeyMapper::getTableName(IdentityType::CUSTOMER_GROUP)
        );
    }

    public function testGetTableNameReturnsImageTableForConfigGroupImageType(): void
    {
        self::assertSame(
            'jtl_connector_link_image',
            PrimaryKeyMapper::getTableName(IdentityType::CONFIG_GROUP_IMAGE)
        );
    }

    public function testGetTableNameReturnsImageTableForProductVariationValueImageType(): void
    {
        self::assertSame(
            'jtl_connector_link_image',
            PrimaryKeyMapper::getTableName(IdentityType::PRODUCT_VARIATION_VALUE_IMAGE)
        );
    }

    public function testGetTableNameReturnsImageTableForSpecificImageType(): void
    {
        self::assertSame(
            'jtl_connector_link_image',
            PrimaryKeyMapper::getTableName(IdentityType::SPECIFIC_IMAGE)
        );
    }

    public function testGetTableNameReturnsImageTableForSpecificValueImageType(): void
    {
        self::assertSame(
            'jtl_connector_link_image',
            PrimaryKeyMapper::getTableName(IdentityType::SPECIFIC_VALUE_IMAGE)
        );
    }

    public function testGetTableNameReturnsImageTableForManufacturerImageType(): void
    {
        self::assertSame(
            'jtl_connector_link_image',
            PrimaryKeyMapper::getTableName(IdentityType::MANUFACTURER_IMAGE)
        );
    }

    public function testGetTableNameReturnsImageTableForCategoryImageType(): void
    {
        self::assertSame(
            'jtl_connector_link_image',
            PrimaryKeyMapper::getTableName(IdentityType::CATEGORY_IMAGE)
        );
    }

    public function testGetTableNameReturnsImageTableForProductImageType(): void
    {
        self::assertSame(
            'jtl_connector_link_image',
            PrimaryKeyMapper::getTableName(IdentityType::PRODUCT_IMAGE)
        );
    }

    public function testGetTableNameReturnsManufacturerTableForManufacturerType(): void
    {
        self::assertSame(
            'jtl_connector_link_manufacturer',
            PrimaryKeyMapper::getTableName(IdentityType::MANUFACTURER)
        );
    }

    public function testGetTableNameReturnsCustomerOrderTableForCustomerOrderType(): void
    {
        self::assertSame(
            'jtl_connector_link_customer_order',
            PrimaryKeyMapper::getTableName(IdentityType::CUSTOMER_ORDER)
        );
    }

    public function testGetTableNameReturnsPaymentTableForPaymentType(): void
    {
        self::assertSame(
            'jtl_connector_link_payment',
            PrimaryKeyMapper::getTableName(IdentityType::PAYMENT)
        );
    }

    public function testGetTableNameReturnsProductTableForProductType(): void
    {
        self::assertSame(
            'jtl_connector_link_product',
            PrimaryKeyMapper::getTableName(IdentityType::PRODUCT)
        );
    }

    public function testGetTableNameReturnsSpecificTableForSpecificType(): void
    {
        self::assertSame(
            'jtl_connector_link_specific',
            PrimaryKeyMapper::getTableName(IdentityType::SPECIFIC)
        );
    }

    public function testGetTableNameReturnsSpecificValueTableForSpecificValueType(): void
    {
        self::assertSame(
            'jtl_connector_link_specific_value',
            PrimaryKeyMapper::getTableName(IdentityType::SPECIFIC_VALUE)
        );
    }

    public function testGetTableNameReturnsTaxClassTableForTaxClassType(): void
    {
        self::assertSame(
            'jtl_connector_link_tax_class',
            PrimaryKeyMapper::getTableName(IdentityType::TAX_CLASS)
        );
    }

    public function testGetTableNameReturnsNullForUnknownType(): void
    {
        self::assertNull(PrimaryKeyMapper::getTableName(0));
    }

    public function testGetTableNameReturnsNullForArbitraryUnmappedInteger(): void
    {
        self::assertNull(PrimaryKeyMapper::getTableName(999999));
    }

    // =========================================================================
    // Part 2: DB-dependent methods – use a Db mock
    // =========================================================================

    /** @return MockObject&Db */
    private function createDbMock(): MockObject
    {
        return $this->createMock(Db::class);
    }

    // -------------------------------------------------------------------------
    // getHostId()
    // -------------------------------------------------------------------------

    public function testGetHostIdWithKnownTypeAndValidValueReturnsInteger(): void
    {
        $db = $this->createDbMock();
        $db->method('getValue')->willReturn('42');

        $mapper = new TestablePrimaryKeyMapper($db);
        self::assertSame(42, $mapper->getHostId(IdentityType::PRODUCT, 'ep1'));
    }

    public function testGetHostIdWithKnownTypeAndFalseValueReturnsZero(): void
    {
        $db = $this->createDbMock();
        $db->method('getValue')->willReturn(false);

        $mapper = new TestablePrimaryKeyMapper($db);
        // false ?: null = null → (int)null = 0
        self::assertSame(0, $mapper->getHostId(IdentityType::PRODUCT, 'ep1'));
    }

    public function testGetHostIdWithKnownTypeAndZeroStringValueReturnsZero(): void
    {
        $db = $this->createDbMock();
        $db->method('getValue')->willReturn('0');

        $mapper = new TestablePrimaryKeyMapper($db);
        // '0' ?: null = null → (int)null = 0
        self::assertSame(0, $mapper->getHostId(IdentityType::PRODUCT, 'ep1'));
    }

    public function testGetHostIdWithKnownTypeAndEmptyStringValueReturnsZero(): void
    {
        $db = $this->createDbMock();
        $db->method('getValue')->willReturn('');

        $mapper = new TestablePrimaryKeyMapper($db);
        // '' ?: null = null → (int)null = 0
        self::assertSame(0, $mapper->getHostId(IdentityType::PRODUCT, 'ep1'));
    }

    public function testGetHostIdWithUnknownTypeReturnsZeroWithoutCallingDb(): void
    {
        $db = $this->createDbMock();
        $db->expects(self::never())->method('getValue');

        $mapper = new TestablePrimaryKeyMapper($db);
        self::assertSame(0, $mapper->getHostId(0, 'ep1'));
    }

    // -------------------------------------------------------------------------
    // getEndpointId()
    // -------------------------------------------------------------------------

    public function testGetEndpointIdWithKnownTypeAndValidValueReturnsString(): void
    {
        $db = $this->createDbMock();
        $db->method('getValue')->willReturn('42');

        $mapper = new TestablePrimaryKeyMapper($db);
        self::assertSame('42', $mapper->getEndpointId(IdentityType::PRODUCT, 1));
    }

    public function testGetEndpointIdWithKnownTypeAndFalseValueReturnsNull(): void
    {
        $db = $this->createDbMock();
        $db->method('getValue')->willReturn(false);

        $mapper = new TestablePrimaryKeyMapper($db);
        // (string)false = '' → '' ?: null = null
        self::assertNull($mapper->getEndpointId(IdentityType::PRODUCT, 1));
    }

    public function testGetEndpointIdWithKnownTypeAndZeroStringValueReturnsNull(): void
    {
        $db = $this->createDbMock();
        $db->method('getValue')->willReturn('0');

        $mapper = new TestablePrimaryKeyMapper($db);
        // (string)'0' = '0' → '0' ?: null = null
        self::assertNull($mapper->getEndpointId(IdentityType::PRODUCT, 1));
    }

    public function testGetEndpointIdWithKnownTypeAndEmptyStringValueReturnsNull(): void
    {
        $db = $this->createDbMock();
        $db->method('getValue')->willReturn('');

        $mapper = new TestablePrimaryKeyMapper($db);
        self::assertNull($mapper->getEndpointId(IdentityType::PRODUCT, 1));
    }

    public function testGetEndpointIdWithUnknownTypeReturnsNullWithoutCallingDb(): void
    {
        $db = $this->createDbMock();
        $db->expects(self::never())->method('getValue');

        $mapper = new TestablePrimaryKeyMapper($db);
        self::assertNull($mapper->getEndpointId(0, 1));
    }

    // -------------------------------------------------------------------------
    // save()
    // -------------------------------------------------------------------------

    public function testSaveWithKnownTypeCallsDbExecuteWithInsertIgnoreAndReturnsTrue(): void
    {
        $db = $this->createDbMock();
        $db->expects(self::once())
            ->method('execute')
            ->with(self::stringContains('INSERT IGNORE INTO jtl_connector_link_product'))
            ->willReturn(true);

        $mapper = new TestablePrimaryKeyMapper($db);
        self::assertTrue($mapper->save(IdentityType::PRODUCT, 'ep1', 99));
    }

    public function testSaveWithKnownTypeAndDbReturningFalseReturnsFalse(): void
    {
        $db = $this->createDbMock();
        $db->method('execute')->willReturn(false);

        $mapper = new TestablePrimaryKeyMapper($db);
        self::assertFalse($mapper->save(IdentityType::PRODUCT, 'ep1', 99));
    }

    public function testSaveWithUnknownTypeReturnsFalseAndDoesNotCallDb(): void
    {
        $db = $this->createDbMock();
        $db->expects(self::never())->method('execute');

        $mapper = new TestablePrimaryKeyMapper($db);
        self::assertFalse($mapper->save(0, 'ep1', 99));
    }

    public function testSaveEmbeddsEndpointIdAndHostIdInSqlStatement(): void
    {
        $capturedSql = null;

        $db = $this->createDbMock();
        $db->method('execute')
            ->willReturnCallback(function (mixed $sql) use (&$capturedSql): bool {
                $capturedSql = (string)$sql;
                return true;
            });

        $mapper = new TestablePrimaryKeyMapper($db);
        $mapper->save(IdentityType::PRODUCT, 'my_endpoint', 123);

        self::assertNotNull($capturedSql);
        self::assertStringContainsString('my_endpoint', $capturedSql);
        self::assertStringContainsString('123', $capturedSql);
    }

    // -------------------------------------------------------------------------
    // delete()
    // -------------------------------------------------------------------------

    public function testDeleteWithUnknownTypeReturnsFalseAndDoesNotCallDb(): void
    {
        $db = $this->createDbMock();
        $db->expects(self::never())->method('execute');

        $mapper = new TestablePrimaryKeyMapper($db);
        self::assertFalse($mapper->delete(0, 'ep1', 1));
    }

    public function testDeleteWithEndpointIdOnlyIncludesEndpointIdInWhereClause(): void
    {
        $capturedSql = null;

        $db = $this->createDbMock();
        $db->method('execute')
            ->willReturnCallback(function (mixed $sql) use (&$capturedSql): bool {
                $capturedSql = (string)$sql;
                return true;
            });

        $mapper = new TestablePrimaryKeyMapper($db);
        $mapper->delete(IdentityType::PRODUCT, 'ep1', null);

        self::assertNotNull($capturedSql);
        self::assertStringContainsString('endpoint_id', $capturedSql);
        self::assertStringContainsString('ep1', $capturedSql);
        self::assertStringNotContainsString('host_id', $capturedSql);
    }

    public function testDeleteWithHostIdOnlyIncludesHostIdInWhereClause(): void
    {
        $capturedSql = null;

        $db = $this->createDbMock();
        $db->method('execute')
            ->willReturnCallback(function (mixed $sql) use (&$capturedSql): bool {
                $capturedSql = (string)$sql;
                return true;
            });

        $mapper = new TestablePrimaryKeyMapper($db);
        $mapper->delete(IdentityType::PRODUCT, null, 42);

        self::assertNotNull($capturedSql);
        self::assertStringContainsString('host_id', $capturedSql);
        self::assertStringContainsString('42', $capturedSql);
        self::assertStringNotContainsString('endpoint_id', $capturedSql);
    }

    public function testDeleteWithBothEndpointIdAndHostIdIncludesBothInWhereClauseWithAnd(): void
    {
        $capturedSql = null;

        $db = $this->createDbMock();
        $db->method('execute')
            ->willReturnCallback(function (mixed $sql) use (&$capturedSql): bool {
                $capturedSql = (string)$sql;
                return true;
            });

        $mapper = new TestablePrimaryKeyMapper($db);
        $mapper->delete(IdentityType::PRODUCT, 'ep1', 42);

        self::assertNotNull($capturedSql);
        self::assertStringContainsString('endpoint_id', $capturedSql);
        self::assertStringContainsString('ep1', $capturedSql);
        self::assertStringContainsString('host_id', $capturedSql);
        self::assertStringContainsString('42', $capturedSql);
        self::assertStringContainsString('AND', $capturedSql);
    }

    public function testDeleteWithNeitherEndpointIdNorHostIdBuildsWhereClauseWithoutConditions(): void
    {
        $capturedSql = null;

        $db = $this->createDbMock();
        $db->method('execute')
            ->willReturnCallback(function (mixed $sql) use (&$capturedSql): bool {
                $capturedSql = (string)$sql;
                return true;
            });

        $mapper = new TestablePrimaryKeyMapper($db);
        $mapper->delete(IdentityType::PRODUCT, null, null);

        self::assertNotNull($capturedSql);
        // Both arrays are empty → implode produces '' → where() ignores it
        self::assertStringNotContainsString('endpoint_id', $capturedSql);
        self::assertStringNotContainsString('host_id', $capturedSql);
    }

    public function testDeleteWithZeroHostIdDoesNotAddHostIdCondition(): void
    {
        // hostId = 0 is falsy → if ($hostId) is false → not added to WHERE
        $capturedSql = null;

        $db = $this->createDbMock();
        $db->method('execute')
            ->willReturnCallback(function (mixed $sql) use (&$capturedSql): bool {
                $capturedSql = (string)$sql;
                return true;
            });

        $mapper = new TestablePrimaryKeyMapper($db);
        $mapper->delete(IdentityType::PRODUCT, null, 0);

        self::assertNotNull($capturedSql);
        self::assertStringNotContainsString('host_id', $capturedSql);
    }

    public function testDeleteCallsDbExecuteAndForwardsReturnValue(): void
    {
        $db = $this->createDbMock();
        $db->expects(self::once())
            ->method('execute')
            ->willReturn(true);

        $mapper = new TestablePrimaryKeyMapper($db);
        self::assertTrue($mapper->delete(IdentityType::PRODUCT, 'ep1', 1));
    }

    // -------------------------------------------------------------------------
    // clear()
    // -------------------------------------------------------------------------

    public function testClearCallsDbExecuteOncePerTableAndAlwaysReturnsTrue(): void
    {
        $db = $this->createDbMock();
        // There are 12 table names in PrimaryKeyMapper::$tableNames
        $db->expects(self::exactly(12))
            ->method('execute')
            ->with(self::stringStartsWith('TRUNCATE TABLE '))
            ->willReturn(true);

        $mapper = new TestablePrimaryKeyMapper($db);
        self::assertTrue($mapper->clear());
    }

    public function testClearReturnsTrueEvenWhenDbExecuteReturnsFalse(): void
    {
        $db = $this->createDbMock();
        $db->method('execute')->willReturn(false);

        $mapper = new TestablePrimaryKeyMapper($db);
        // clear() always returns true regardless of db->execute() result
        self::assertTrue($mapper->clear());
    }
}
