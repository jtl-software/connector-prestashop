<?php


namespace Tests;


use function foo\func;

class ProductTest extends \Jtl\Connector\IntegrationTests\Integration\ProductTest
{
    public function getIgnoreArray()
    {
        return [
            'i18ns.0.measurementUnitName',
            'i18ns.0.unitName',
            'customerGroups',
            'invisibilities',
            'considerStock',
            'modified',
            'permitNegativeStock', //Is set default to true, otherwise products without Stock handling couldn't be bought
            'minBestBeforeDate',
            'newReleaseDate',
            'nextAvailableInflowDate',
            'creationDate',
            'basePriceUnitName',
            'attributes.0', // onlineOnly Attribute is sent by pull
            'attributes.1', // isActive Attribute is sent by pull
            'isActive', //Is Handled via attributes
            'asin',
            'taric',
            'variations.0.values.0.stockLevel',
            'variations.0.values.0.extraWeight',
            'variations.0.values.0.sort',
            'keywords',
            'sort',
            'variations.0.sort',
            'variations.0.type',
            'unNumber',
            'isNewProduct',
            'productWeight',
            'isSerialNumber',
            'serialNumber', 
            'basePriceDivisor', 
            'basePriceFactor', 
            'basePriceQuantity', 
            'basePriceUnitCode', 
            'note', 
            'recommendedRetailPrice', 
            'isBatch', 
            'isBestBefore', 
            'isDivisible', 
            'manufacturerNumber', 
            'measurementQuantity', 
            'measurementUnitCode', 
            'nextAvailableInflowQuantity', 
            'packagingQuantity', 
            'supplierDeliveryTime', 
            'supplierStockLevel',
            'originCountry',
            'minimumQuantity',
            'prices.0.items.0.quantity',
            
        ];
    }
    
    public function testProductCustomerGroupPackagingQuantityPush() {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }
    
    public function testProductConfigGroupPush() {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }
    
    public function testProductPartsListPush() {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }
    
    public function testProductSpecialPricePush() {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }
    
    public function testProductSpecificPush()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }
    
    public function testProductVariationPush()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }
    
    public function testProductWarehousePush()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }
}
