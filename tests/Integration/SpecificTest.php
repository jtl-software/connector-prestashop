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
use jtl\Connector\Model\Specific;
use jtl\Connector\Model\SpecificI18n;
use jtl\Connector\Model\SpecificValue;
use jtl\Connector\Model\SpecificValueI18n;
use Tests\PrestashopConnectorTestCase;

class SpecificTest extends PrestashopConnectorTestCase
{
    public function testSpecificBasicPush()
    {
        $specific = new Specific();
            $i18n = new SpecificI18n();
            $i18n->setSpecificId(new Identity('', 1));
            $i18n->setLanguageISO('ger');
            $i18n->setName('');
        $specific->addI18n($i18n);
        $specific->setId(new Identity('', 1));
        $specific->setIsGlobal(true);
        $specific->setSort(0);
        $specific->setType('');
        
        $this->pushCoreModels([$specific], true);
    }
    
    public function testSpecificI18nPush()
    {
        $specific = new Specific();
            $i18n = new SpecificI18n();
            $i18n->setSpecificId(new Identity('', 1));
            $i18n->setLanguageISO('ger');
            $i18n->setName('');
        $specific->addI18n($i18n);
    
        $this->pushCoreModels([$specific], true);
    }
    
    public function testSpecificValuesPush()
    {
        $specific = new Specific();
            $i18n = new SpecificI18n();
            $i18n->setSpecificId(new Identity('', 1));
            $i18n->setLanguageISO('ger');
            $i18n->setName('');
        $specific->addI18n($i18n);
            $value = new SpecificValue();
            $value->setId(new Identity('', 1));
            $value->setSpecificId(new Identity('', 1));
            $value->setSort(0);
                $i18n = new SpecificValueI18n();
                $i18n->setSpecificValueId(new Identity('', 1));
                $i18n->setDescription('');
                $i18n->setLanguageISO('ger');
                $i18n->setMetaDescription('');
                $i18n->setMetaKeywords('');
                $i18n->setTitleTag('');
                $i18n->setUrlPath('');
                $i18n->setValue('');
            $value->addI18n($i18n);
        $specific->addValue($value);
    
        $this->pushCoreModels([$specific], true);
    }
}
