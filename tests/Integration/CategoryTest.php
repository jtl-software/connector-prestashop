<?php

namespace Tests\Integration;

use jtl\Connector\Model\Category;
use jtl\Connector\Model\CategoryAttr;
use jtl\Connector\Model\CategoryAttrI18n;
use jtl\Connector\Model\CategoryI18n;
use jtl\Connector\Model\CategoryInvisibility;
use jtl\Connector\Model\Identity;
use jtl\Connector\Model\CategoryCustomerGroup;
use Tests\PrestashopConnectorTestCase;

class CategoryTest extends PrestashopConnectorTestCase
{
    public function testCategoryBasicPush()
    {
        $category = new Category();
            $i18n = new CategoryI18n();
            $i18n->setCategoryId(new Identity('', 1));
            $i18n->setLanguageISO('ger');
            $i18n->setName('test');
        $category->addI18n($i18n);
        $category->setId(new Identity('', 1));
        $category->setParentCategoryId(new Identity('', 1));
        $category->setIsActive(true);
        $category->setLevel(0);
        $category->setSort(0);
        
        $this->pushCoreModels([$category], true);
    }
    
    public function testCategoryAttributesPush()
    {
        $category = new Category();
            $i18n = new CategoryI18n();
            $i18n->setCategoryId(new Identity('', 1));
            $i18n->setLanguageISO('ger');
            $i18n->setName('test');
        $category->addI18n($i18n);
            $attribute = new CategoryAttr();
            $attribute->setCategoryId(new Identity('', 1));
            $attribute->setId(new Identity('', 1));
            $attribute->setIsCustomProperty(true);
            $attribute->setIsTranslated(true);
                $i18n = new CategoryAttrI18n();
                $i18n->setCategoryAttrId(new Identity('', 1));
                $i18n->setLanguageISO('');
                $i18n->setName('');
                $i18n->setValue('');
            $attribute->setI18ns([$i18n]);
        $category->setAttributes([$attribute]);
        
        $this->pushCoreModels([$category], true);
    }
    
    public function testCategoryCustomGroupsPush()
    {
        $category = new Category();
            $i18n = new CategoryI18n();
            $i18n->setCategoryId(new Identity('', 1));
            $i18n->setLanguageISO('ger');
            $i18n->setName('test');
        $category->addI18n($i18n);
            $customerGroup = new CategoryCustomerGroup();
            $customerGroup->setCategoryId(new Identity('', 1));
            $customerGroup->setCustomerGroupId(new Identity('', 1));
            $customerGroup->setDiscount(0.0);
        $category->setCustomerGroups([$customerGroup]);
    
        $this->pushCoreModels([$category], true);
    }
    
    public function testCategoryI18nsPush()
    {
        $category = new Category();
            $i18n = new CategoryI18n();
            $i18n->setCategoryId(new Identity('', 1));
            $i18n->setDescription('');
            $i18n->setLanguageISO('ger');
            $i18n->setMetaDescription('');
            $i18n->setMetaKeywords('');
            $i18n->setName('TEST');
            $i18n->setTitleTag('');
            $i18n->setUrlPath('');
        $category->addI18n($i18n);
    
        $this->pushCoreModels([$category], true);
    }
    
    public function testCategoryInvisibilitiesPush()
    {
        $category = new Category();
            $i18n = new CategoryI18n();
            $i18n->setCategoryId(new Identity('', 1));
            $i18n->setLanguageISO('ger');
            $i18n->setName('test');
        $category->addI18n($i18n);
            $invisibility = new CategoryInvisibility();
            $invisibility->setCategoryId(new Identity('', 1));
            $invisibility->setCustomerGroupId(new Identity('', 1));
        $category->setInvisibilities([$invisibility]);
    
        $this->pushCoreModels([$category], true);
    }
}
