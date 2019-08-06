<?php

namespace Tests\Integration;

use jtl\Connector\Model\Identity;
use jtl\Connector\Model\Product;
use jtl\Connector\Model\ProductI18n;
use jtl\Connector\Model\ProductPrice;
use jtl\Connector\Model\ProductStockLevel;
use jtl\Connector\Presta\Mapper\PrimaryKeyMapper;
use jtl\Connector\Serializer\JMS\SerializerBuilder;
use Tests\ConnectorTestCase;

class ProductTest extends ConnectorTestCase
{
    
    /**
     *
     */
    public function testProductPush()
    {
        $product = new Product();
        $product->getId()->setHost(1);
        $product->setStockLevel(new ProductStockLevel());
        $product->addPrice(new ProductPrice());
        $productI18N = new ProductI18n();
        $productI18N->setLanguageISO('de');
        $productI18N->setDescription('Normale Beschreibung');
        $productI18N->setName("Produktname");
        $productI18N->setProductId($product->getId());
        $productI18N->setShortDescription('Kurze Beschreibung');
        $product->addI18n($productI18N);
        
        $response = $this->pushCoreModels([$product], true);
        
        $i18nAssertions = [
            'LanguageISO' => 'Equals',
            'Description' => 'Equals',
            'Name' => 'Equals',
        ];
        
        $endpointId = $response[0]->getId()->getEndpoint();
        $this->assertNotEmpty($endpointId);
        $endpointId = $response[0]->getI18ns()[0]->getProductId()->getEndpoint();
        $this->assertNotEmpty($endpointId);
        
        $response[0]->getI18ns()[0]->getProductId()->setEndpoint('');
        
        $this->assertCoreModel($product->getI18ns()[0], $response[0]->getI18ns()[0]);//, $i18nAssertions);
    }
    
    public function productDataProvider()
    {
        return [
            ['data', 'more']
        ];
    }
}
