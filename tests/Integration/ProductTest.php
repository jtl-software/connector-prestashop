<?php


namespace Tests;


use function foo\func;

class ProductTest extends \Jtl\Connector\IntegrationTests\Integration\ProductTest
{
    public function getIgnoreArray()
    {
        return [
            'i18ns',
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
            
            'prices.0.items.0.netPrice', //Needs fixing
            'prices.0.items.0.quantity', //Needs fixing
            
            'variations.0.type', //Needs fixing
            'variations.0.sort', //Needs fixing
            'variations.0.values.0.stockLevel',
            'variations.0.values.0.ean', //Needs fixing
            'variations.0.values.0.extraWeight', //Needs fixing
            'variations.0.values.0.sku', //Needs fixing
            'variations.0.values.0.sort', //Needs fixing
            
            'asin', //Needs fixing
            'basePriceDivisor', //Needs fixing
            'basePriceFactor', //Needs fixing
            'basePriceQuantity', //Needs fixing
            'basePriceUnitCode', //Needs fixing
            'isBatch', //Needs fixing
            'isBestBefore', //Needs fixing
            'isbn', //Needs fixing
            'isDivisible', //Needs fixing
            'isMasterProduct', //Needs fixing
            'isNewProduct', //Needs fixing
            'isSerialNumber', //Needs fixing
            'keywords', //Needs fixing
            'manufacturerNumber', //Needs fixing
            'measurementQuantity', //Needs fixing
            'measurementUnitCode', //Needs fixing
            'minimumQuantity', //Needs fixing
            'nextAvailableInflowQuantity', //Needs fixing
            'note', //Needs fixing
            'originCountry', //Needs fixing
            'packagingQuantity', //Needs fixing
            'productWeight', //Needs fixing
            'recommendedRetailPrice', //Needs fixing
            'serialNumber', //Needs fixing
            'sort', //Needs fixing
            'supplierDeliveryTime', //Needs fixing
            'supplierStockLevel', //Needs fixing
            'taric', //Needs fixing
            'unNumber', //Needs fixing
            'vat', //Needs fixing
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
