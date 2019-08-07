<?php

namespace Tests\Integration;

use DateTime;
use jtl\Connector\Model\Checksum;
use jtl\Connector\Model\CustomerGroupPackagingQuantity;
use jtl\Connector\Model\FileUpload;
use jtl\Connector\Model\FileUploadI18n;
use jtl\Connector\Model\Identity;
use jtl\Connector\Model\Manufacturer;
use jtl\Connector\Model\ManufacturerI18n;
use jtl\Connector\Model\Product;
use jtl\Connector\Model\ProductAttr;
use jtl\Connector\Model\ProductAttrI18n;
use jtl\Connector\Model\Product2Category;
use jtl\Connector\Model\ProductConfigGroup;
use jtl\Connector\Model\ProductI18n;
use jtl\Connector\Model\ProductInvisibility;
use jtl\Connector\Model\ProductMediaFile;
use jtl\Connector\Model\ProductMediaFileAttr;
use jtl\Connector\Model\ProductMediaFileAttrI18n;
use jtl\Connector\Model\ProductMediaFileI18n;
use jtl\Connector\Model\ProductPartsList;
use jtl\Connector\Model\ProductPrice;
use jtl\Connector\Model\ProductPriceItem;
use jtl\Connector\Model\ProductSpecialPrice;
use jtl\Connector\Model\ProductSpecialPriceItem;
use jtl\Connector\Model\ProductSpecific;
use jtl\Connector\Model\ProductStockLevel;
use jtl\Connector\Model\ProductVarCombination;
use jtl\Connector\Model\ProductVariation;
use jtl\Connector\Model\ProductVariationI18n;
use jtl\Connector\Model\ProductVariationInvisibility;
use jtl\Connector\Model\ProductVariationValue;
use jtl\Connector\Model\ProductVariationValueExtraCharge;
use jtl\Connector\Model\ProductVariationValueI18n;
use jtl\Connector\Model\ProductVariationValueInvisibility;
use jtl\Connector\Model\ProductWarehouseInfo;
use Tests\ConnectorTestCase;
use Tests\PrestashopConnectorTestCase;

class ProductTest extends PrestashopConnectorTestCase
{
    public function testProductBasicPush()
    {
        $product = new Product();
        $product->setBasePriceUnitId(new Identity('', 1));
        $product->setId(new Identity('', 1));
        $product->setStockLevel(new ProductStockLevel());
        $product->addPrice(new ProductPrice());
        $product->setManufacturerId(new Identity('', 1));
        $product->setMasterProductId(new Identity('', 1));
        $product->setMeasurementUnitId(new Identity('', 1));
        $product->setPartsListId(new Identity('', 1));
        $product->setProductTypeId(new Identity('', 1));
        $product->setShippingClassId(new Identity('', 1));
        $product->setUnitId(new Identity('', 1));
        $product->setAsin('');
        $product->setAvailableFrom(new DateTime());
        $product->setBasePriceDivisor(0.0);
        $product->setBasePriceFactor(0.0);
        $product->setBasePriceQuantity(0.0);
        $product->setBasePriceUnitCode('');
        $product->setBasePriceUnitName('');
        $product->setConsiderBasePrice(false);
        $product->setConsiderStock(false);
        $product->setConsiderVariationStock(false);
        $product->setCreationDate(new DateTime());
        $product->setEan('');
        $product->setEpid('');
        $product->setHazardIdNumber('');
        $product->setHeight(0.0);
        $product->setIsActive(false);
        $product->setIsBatch(false);
        $product->setIsBestBefore(false);
        $product->setIsbn('');
        $product->setIsDivisible(false);
        $product->setIsMasterProduct(false);
        $product->setIsNewProduct(false);
        $product->setIsSerialNumber(false);
        $product->setIsTopProduct(false);
        $product->setKeywords('');
        $product->setLength(0.0);
        $product->setManufacturerNumber('');
        $product->setMeasurementQuantity(0.0);
        $product->setMeasurementUnitCode('');
        $product->setMinBestBeforeDate(new DateTime());
        $product->setMinimumOrderQuantity(0.0);
        $product->setMinimumQuantity(0.0);
        $product->setModified(new DateTime());
        $product->setNewReleaseDate(new DateTime());
        $product->setNextAvailableInflowDate(new DateTime());
        $product->setNextAvailableInflowQuantity(0.0);
        $product->setNote('');
        $product->setOriginCountry('');
        $product->setPackagingQuantity(0.0);
        $product->setPermitNegativeStock(false);
        $product->setProductWeight(0.0);
        $product->setPurchasePrice(0.0);
        $product->setRecommendedRetailPrice(0.0);
        $product->setSerialNumber('');
        $product->setShippingWeight(0.0);
        $product->setSku('');
        $product->setSort(0);
        $product->setSupplierDeliveryTime(0);
        $product->setSupplierStockLevel(0.0);
        $product->setTaric('');
        $product->setUnNumber('');
        $product->setUpc('');
        $product->setVat(0.0);
        $product->setWidth(0.0);
    
        $this->pushCoreModels([$product], true);
    }
    
    public function testProductManufacturerPush(){
        $product = new Product();
        $product->setId(new Identity('', 1));
        $product->setStockLevel(new ProductStockLevel());
        $product->addPrice(new ProductPrice());
            $manufacturer = new Manufacturer();
            $manufacturer->setId(new Identity('', 1));
            $manufacturer->setName('');
            $manufacturer->setSort(0);
            $manufacturer->setUrlPath('');
            $manufacturer->setWebsiteUrl('');
                $manufacturerI18n = new ManufacturerI18n();
                $manufacturerI18n->setManufacturerId(new Identity('', 1));
                $manufacturerI18n->setDescription('');
                $manufacturerI18n->setLanguageISO('');
                $manufacturerI18n->setMetaDescription('');
                $manufacturerI18n->setMetaKeywords('');
                $manufacturerI18n->setTitleTag('');
            $manufacturer->setI18ns([$manufacturerI18n]);
        $product->setManufacturer($manufacturer);
    
        $this->pushCoreModels([$product], true);
    }
    
    public function testProductStockLevelPush(){
        $product = new Product();
        $product->setId(new Identity('', 1));
        $product->setStockLevel(new ProductStockLevel());
        $product->addPrice(new ProductPrice());
            $stockLevel = new ProductStockLevel();
            $stockLevel->setProductId(new Identity('', 1));
            $stockLevel->setStockLevel(0.0);
        $product->setStockLevel($stockLevel);
    
        $this->pushCoreModels([$product], true);
    }
    
    public function testProductAttributePush(){
        $product = new Product();
        $product->setId(new Identity('', 1));
        $product->setStockLevel(new ProductStockLevel());
        $product->addPrice(new ProductPrice());
            $attribute = new ProductAttr();
            $attribute->setId(new Identity('', 1));
            $attribute->setProductId(new Identity('', 1));
            $attribute->setIsCustomProperty(false);
            $attribute->setIsTranslated(false);
                $attributeI18n = new ProductAttrI18n();
                $attributeI18n->setProductAttrId(new Identity('', 1));
                $attributeI18n->setLanguageISO('');
                $attributeI18n->setName('');
                $attributeI18n->setValue('');
            $attribute->setI18ns([$attributeI18n]);
        $product->setAttributes([$attribute]);
    
        $this->pushCoreModels([$product], true);
    }
    
    public function testProductToCategoryPush(){
        $product = new Product();
        $product->setId(new Identity('', 1));
        $product->setStockLevel(new ProductStockLevel());
        $product->addPrice(new ProductPrice());
            $productsToCategories = new Product2Category();
            $productsToCategories->setCategoryId(new Identity('', 1));
            $productsToCategories->setId(new Identity('', 1));
            $productsToCategories->setProductId(new Identity('', 1));
        $product->setCategories([$productsToCategories]);
    
        $this->pushCoreModels([$product], true);
    }
    
    public function testProductChecksumPush(){
        $product = new Product();
        $product->setId(new Identity('', 1));
        $product->setStockLevel(new ProductStockLevel());
        $product->addPrice(new ProductPrice());
            $checksum = new Checksum();
            $checksum->setForeignKey(new Identity('', 1));
            $checksum->setEndpoint('');
            $checksum->setHasChanged(false);
            $checksum->setHost('');
            $checksum->setType(0);
        $product->setChecksums([$checksum]);
    
        $this->pushCoreModels([$product], true);
    }
    
    public function testProductConfigGroupPush(){
        $product = new Product();
        $product->setId(new Identity('', 1));
        $product->setStockLevel(new ProductStockLevel());
        $product->addPrice(new ProductPrice());
            $configGroup = new ProductConfigGroup();
            $configGroup->setConfigGroupId(new Identity('', 1));
            $configGroup->setProductId(new Identity('', 1));
            $configGroup->setSort(0);
        $product->setConfigGroups([$configGroup]);
    
        $this->pushCoreModels([$product], true);
    }
    
    public function testProductCustomerGroupPackagingQuantityPush(){
        $product = new Product();
        $product->setId(new Identity('', 1));
        $product->setStockLevel(new ProductStockLevel());
        $product->addPrice(new ProductPrice());
            $packagingQuantity = new CustomerGroupPackagingQuantity();
            $packagingQuantity->setCustomerGroupId(new Identity('', 1));
            $packagingQuantity->setProductId(new Identity('', 1));
            $packagingQuantity->setMinimumOrderQuantity(0.0);
            $packagingQuantity->setPackagingQuantity(0.0);
        $product->setCustomerGroupPackagingQuantities([$packagingQuantity]);
    
        $this->pushCoreModels([$product], true);
    }
    
    public function testProductFileUploadPush(){
        $product = new Product();
        $product->setId(new Identity('', 1));
        $product->setStockLevel(new ProductStockLevel());
        $product->addPrice(new ProductPrice());
            $fileUpload = new FileUpload();
            $fileUpload->setId(new Identity('', 1));
            $fileUpload->setProductId(new Identity('', 1));
            $fileUpload->setFileType('');
            $fileUpload->setIsRequired(false);
                $fileUploadI18n = new FileUploadI18n();
                $fileUploadI18n->setDescription('');
                $fileUploadI18n->setFileUploadId(0);
                $fileUploadI18n->setLanguageISO('');
                $fileUploadI18n->setName('');
            $fileUpload->setI18ns([$fileUploadI18n]);
        $product->setFileDownloads([$fileUpload]);
    
        $this->pushCoreModels([$product], true);
    }
    
    public function testProductI18nPush(){
        $product = new Product();
        $product->setId(new Identity('', 1));
        $product->setStockLevel(new ProductStockLevel());
        $product->addPrice(new ProductPrice());
            $productI18n = new ProductI18n();
            $productI18n->setProductId(new Identity('', 1));
            $productI18n->setDeliveryStatus('');
            $productI18n->setDescription('');
            $productI18n->setLanguageISO('');
            $productI18n->setMeasurementUnitName('');
            $productI18n->setMetaDescription('');
            $productI18n->setMetaKeywords('');
            $productI18n->setName('');
            $productI18n->setShortDescription('');
            $productI18n->setTitleTag('');
            $productI18n->setUnitName('');
            $productI18n->setUrlPath('');
        $product->setI18ns([$productI18n]);
    
        $this->pushCoreModels([$product], true);
    }
    
    public function testProductInvisibilityPush(){
        $product = new Product();
        $product->setId(new Identity('', 1));
        $product->setStockLevel(new ProductStockLevel());
        $product->addPrice(new ProductPrice());
            $invisibility = new ProductInvisibility();
            $invisibility->setCustomerGroupId(new Identity('', 1));
            $invisibility->setProductId(new Identity('', 1));
        $product->setInvisibilities([$invisibility]);
    
        $this->pushCoreModels([$product], true);
    }
    
    public function testProductMediaFilePush(){
        $product = new Product();
        $product->setId(new Identity('', 1));
        $product->setStockLevel(new ProductStockLevel());
        $product->addPrice(new ProductPrice());
            $mediaFile = new ProductMediaFile();
            $mediaFile->setId(new Identity('', 1));
            $mediaFile->setProductId(new Identity('', 1));
            $mediaFile->setMediaFileCategory('');
            $mediaFile->setPath('');
            $mediaFile->setSort(0);
            $mediaFile->setType('');
            $mediaFile->setUrl('');
                $mediaFileAttribute = new ProductMediaFileAttr();
                $mediaFileAttribute->setProductMediaFileId(new Identity('', 1));
                    $mediaFileAttributeI18n = new ProductMediaFileAttrI18n();
                    $mediaFileAttributeI18n->setLanguageISO('');
                    $mediaFileAttributeI18n->setName('');
                    $mediaFileAttributeI18n->setValue('');
                $mediaFileAttribute->setI18ns([$mediaFileAttributeI18n]);
            $mediaFile->setAttributes([$mediaFileAttribute]);
                $mediaFileI18n = new ProductMediaFileI18n();
                $mediaFileI18n->setProductMediaFileId(new Identity('', 1));
                $mediaFileI18n->setDescription('');
                $mediaFileI18n->setLanguageISO('');
                $mediaFileI18n->setName('');
            $mediaFile->setI18ns([$mediaFileI18n]);
        $product->setMediaFiles([$mediaFile]);
    }
    
    public function testProductPartsListPush(){
        $product = new Product();
        $product->setId(new Identity('', 1));
        $product->setStockLevel(new ProductStockLevel());
        $product->addPrice(new ProductPrice());
            $partsList= new ProductPartsList();
            $partsList->setId(new Identity('', 1));
            $partsList->setProductId(new Identity('', 1));
            $partsList->setQuantity(0.0);;
        $product->setPartsLists([$partsList]);
    }
    
    public function testProductPricePush(){
        $product = new Product();
        $product->setId(new Identity('', 1));
        $product->setStockLevel(new ProductStockLevel());
        $product->addPrice(new ProductPrice());
            $price = new ProductPrice();
            $price->setCustomerGroupId(new Identity('', 1));
            $price->setCustomerId(new Identity('', 1));
            $price->setId(new Identity('', 1));
            $price->setProductId(new Identity('', 1));
                $priceItem = new ProductPriceItem();
                $priceItem->setProductPriceId(new Identity('', 1));
                $priceItem->setNetPrice(0.0);
                $priceItem->setQuantity(0);;
            $price->setItems([$priceItem]);
        $product->setPrices([$price]);
    }
    
    public function testProductSpecialPricePush(){
        $product = new Product();
        $product->setId(new Identity('', 1));
        $product->setStockLevel(new ProductStockLevel());
        $product->addPrice(new ProductPrice());
            $specialPrice = new ProductSpecialPrice();
            $specialPrice->setId(new Identity('', 1));
            $specialPrice->setProductId(new Identity('', 1));
            $specialPrice->setActiveFromDate(new DateTime());
            $specialPrice->setActiveUntilDate(new DateTime());
            $specialPrice->setConsiderDateLimit(false);
            $specialPrice->setConsiderStockLimit(false);
            $specialPrice->setIsActive(false);
            $specialPrice->setStockLimit(0);
                $specialPriceItem = new ProductSpecialPriceItem();
                $specialPriceItem->setCustomerGroupId(new Identity('', 1));
                $specialPriceItem->setProductSpecialPriceId(new Identity('', 1));
                $specialPriceItem->setPriceNet(0.0);
            $specialPrice->setItems([$specialPriceItem]);
        $product->setSpecialPrices([$specialPrice]);
    }
    
    public function testProductSpecificPush(){
        $product = new Product();
        $product->setId(new Identity('', 1));
        $product->setStockLevel(new ProductStockLevel());
        $product->addPrice(new ProductPrice());
            $specific = new ProductSpecific();
            $specific->setId(new Identity('', 1));
            $specific->setProductId(new Identity('', 1));
            $specific->setSpecificValueId(new Identity('', 1));
        $product->setSpecifics([$specific]);
    }
    
    public function testProductVarCombinationPush(){
        $product = new Product();
        $product->setId(new Identity('', 1));
        $product->setStockLevel(new ProductStockLevel());
        $product->addPrice(new ProductPrice());
            $varCombination = new ProductVarCombination();
            $varCombination->setProductId(new Identity('', 1));
            $varCombination->setProductVariationId(new Identity('', 1));
            $varCombination->setProductVariationValueId(new Identity('', 1));
        $product->setVarCombinations([$varCombination]);
    }
    
    public function testProductVariationPush(){
        $product = new Product();
        $product->setId(new Identity('', 1));
        $product->setStockLevel(new ProductStockLevel());
        $product->addPrice(new ProductPrice());
            $variation = new ProductVariation();
            $variation->setId(new Identity('', 1));
            $variation->setProductId(new Identity('', 1));
            $variation->setSort(0);
            $variation->setType('');
                $variationI18n = new ProductVariationI18n();
                $variationI18n->setProductVariationId(new Identity('', 1));
                $variationI18n->setLanguageISO('');
                $variationI18n->setName('');
            $variation->setI18ns([$variationI18n]);
                $variationInvisibility = new ProductVariationInvisibility();
                $variationInvisibility->setCustomerGroupId(new Identity('', 1));
                $variationInvisibility->setProductVariationId(new Identity('', 1));
            $variation->setInvisibilities([$variationInvisibility]);
                $variationValue = new ProductVariationValue();
                $variationValue->setId(new Identity('', 1));
                $variationValue->setProductVariationId(new Identity('', 1));
                $variationValue->setEan('');
                $variationValue->setExtraWeight(0.0);
                $variationValue->setSku('');
                $variationValue->setSort(0);
                $variationValue->setStockLevel(0.0);
                    $variationValueExtraCharge = new ProductVariationValueExtraCharge();
                    $variationValueExtraCharge->setCustomerGroupId(new Identity('', 1));
                    $variationValueExtraCharge->setProductVariationValueId(new Identity('', 1));
                    $variationValueExtraCharge->setExtraChargeNet(0.0);
                $variationValue->setExtraCharges([$variationValueExtraCharge]);
                    $variationValueI18n = new ProductVariationValueI18n();
                    $variationValueI18n->setProductVariationValueId(new Identity('', 1));
                    $variationValueI18n->setLanguageISO('');
                    $variationValueI18n->setName('');
                $variationValue->setI18ns([$variationValueI18n]);
                    $variationValueInvisibility = new ProductVariationValueInvisibility();
                    $variationValueInvisibility->setCustomerGroupId(new Identity('', 1));
                    $variationValueInvisibility->setProductVariationValueId(new Identity('', 1));
                $variationValue->setInvisibilities([$variationValueInvisibility]);
            $variation->setValues([$variationValue]);
        $product->setVariations([$variation]);
    }
    
    public function testProductWarehousePush(){
        $product = new Product();
        $product->setId(new Identity('', 1));
        $product->setStockLevel(new ProductStockLevel());
        $product->addPrice(new ProductPrice());
            $warehouseInfo = new ProductWarehouseInfo();
            $warehouseInfo->setProductId(new Identity('', 1));
            $warehouseInfo->setwarehouseId(new Identity('', 1));
            $warehouseInfo->setInflowQuantity(0.0);
            $warehouseInfo->setstockLevel(0.0);
        $product->setWarehouseInfo([$warehouseInfo]);
    }
}
