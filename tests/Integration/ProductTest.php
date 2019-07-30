<?php

namespace Tests\Integration;

use jtl\Connector\Model\Product;
use Tests\ConnectorTestCase;

class ProductTest extends ConnectorTestCase
{
    
    /**
     *
     */
    public function testProductPush()
    {
        $product = new Product();
        var_dump($product->toJson());
    }
    
    public function productDataProvider()
    {
        return [
            ['data', 'more']
        ];
    }
}
