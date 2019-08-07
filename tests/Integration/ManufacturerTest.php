<?php

namespace Tests\Integration;

use jtl\Connector\Model\Category;
use jtl\Connector\Model\CategoryAttr;
use jtl\Connector\Model\CategoryAttrI18n;
use jtl\Connector\Model\CategoryI18n;
use jtl\Connector\Model\CategoryInvisibility;
use jtl\Connector\Model\Identity;
use jtl\Connector\Model\CategoryCustomerGroup;
use jtl\Connector\Model\Manufacturer;
use jtl\Connector\Model\ManufacturerI18n;
use Tests\PrestashopConnectorTestCase;

class ManufacturerTest extends PrestashopConnectorTestCase
{
    public function testManufacturerBasicPush()
    {
        $manufacturer = new Manufacturer();
        $manufacturer->setId(new Identity('', 1));
        $manufacturer->setName('Test');
        $manufacturer->setSort(0);
        $manufacturer->setUrlPath('');
        $manufacturer->setWebsiteUrl('');
        
        $this->pushCoreModels([$manufacturer], true);
    }
    
    public function testManufacturerI18nPush()
    {
        $manufacturer = new Manufacturer();
        $manufacturer->setName('Test');
            $i18n = new ManufacturerI18n();
            $i18n->setManufacturerId(new Identity('', 1));
            $i18n->setDescription('');
            $i18n->setLanguageISO('ger');
            $i18n->setMetaDescription('');
            $i18n->setMetaKeywords('');
            $i18n->setTitleTag('');
        $manufacturer->addI18n($i18n);
        
        $this->pushCoreModels([$manufacturer], true);
    }
}
