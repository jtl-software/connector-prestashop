<?php

declare(strict_types=1);

namespace Tests\Unit\Mapper;

use Jtl\Connector\Core\Definition\IdentityType;
use jtl\Connector\Presta\Mapper\PrimaryKeyMapper;
use PHPUnit\Framework\TestCase;

/**
 * @covers \jtl\Connector\Presta\Mapper\PrimaryKeyMapper::getTableName
 */
class PrimaryKeyMapperTest extends TestCase
{
    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function tableNameProvider(): array
    {
        return [
            'category'            => [IdentityType::CATEGORY, 'jtl_connector_link_category'],
            'cross selling'       => [IdentityType::CROSS_SELLING, 'jtl_connector_link_crossselling'],
            'cross selling group' => [IdentityType::CROSS_SELLING_GROUP, 'jtl_connector_link_crossselling_group'],
            'customer'            => [IdentityType::CUSTOMER, 'jtl_connector_link_customer'],
            'customer group'      => [IdentityType::CUSTOMER_GROUP, 'jtl_connector_link_customer'],
            'product image'       => [IdentityType::PRODUCT_IMAGE, 'jtl_connector_link_image'],
            'category image'      => [IdentityType::CATEGORY_IMAGE, 'jtl_connector_link_image'],
            'manufacturer image'  => [IdentityType::MANUFACTURER_IMAGE, 'jtl_connector_link_image'],
            'manufacturer'        => [IdentityType::MANUFACTURER, 'jtl_connector_link_manufacturer'],
            'customer order'      => [IdentityType::CUSTOMER_ORDER, 'jtl_connector_link_customer_order'],
            'payment'             => [IdentityType::PAYMENT, 'jtl_connector_link_payment'],
            'product'             => [IdentityType::PRODUCT, 'jtl_connector_link_product'],
            'specific'            => [IdentityType::SPECIFIC, 'jtl_connector_link_specific'],
            'specific value'      => [IdentityType::SPECIFIC_VALUE, 'jtl_connector_link_specific_value'],
            'tax class'           => [IdentityType::TAX_CLASS, 'jtl_connector_link_tax_class'],
        ];
    }

    /**
     * @dataProvider tableNameProvider
     *
     * @param int    $type
     * @param string $expected
     *
     * @return void
     */
    public function testGetTableNameReturnsExpectedTable(int $type, string $expected): void
    {
        $this->assertSame($expected, PrimaryKeyMapper::getTableName($type));
    }

    /**
     * @return void
     */
    public function testGetTableNameReturnsNullForUnknownType(): void
    {
        $this->assertNull(PrimaryKeyMapper::getTableName(-1));
        $this->assertNull(PrimaryKeyMapper::getTableName(IdentityType::DELIVERY_NOTE));
    }
}
