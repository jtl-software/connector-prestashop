<?php

namespace Tests\Integration;

use DateTime;
use jtl\Connector\Model\Customer;
use jtl\Connector\Model\Identity;
use jtl\Connector\Model\CustomerAttr;
use Tests\PrestashopConnectorTestCase;

/*class CustomerTest extends PrestashopConnectorTestCase
{
    public function testCustomerBasicPush()
    {
        $customer = new Customer();
        $customer->setCustomerGroupId(new Identity('', 1));
        $customer->setId(new Identity('', 1));
        $customer->setAccountCredit(0.0);
        $customer->setBirthday(new DateTime());
        $customer->setCity('');
        $customer->setCompany('');
        $customer->setCountryIso('');
        $customer->setCreationDate(new DateTime());
        $customer->setCustomerNumber('');
        $customer->setDeliveryInstruction('');
        $customer->setDiscount(0.0);
        $customer->setEMail('');
        $customer->setExtraAddressLine('');
        $customer->setFax('');
        $customer->setFirstName('');
        $customer->setHasCustomerAccount(true);
        $customer->setHasNewsletterSubscription(true);
        $customer->setIsActive(true);
        $customer->setLanguageISO('ger');
        $customer->setLastName('');
        $customer->setMobile('');
        $customer->setNote('');
        $customer->setOrigin('');
        $customer->setPhone('');
        $customer->setSalutation('');
        $customer->setState('');
        $customer->setStreet('');
        $customer->setTitle('');
        $customer->setVatNumber('');
        $customer->setWebsiteUrl('');
        $customer->setZipCode('');
        
        $this->pushCoreModels([$customer], true);
    }
    
    public function testCustomerAttributePush()
    {
        $customer = new Customer();
            $attribute = new CustomerAttr();
            $attribute->setCustomerId(new Identity('', 1));
            $attribute->setKey('');
            $attribute->setValue('');
        $customer->addAttribute($attribute);
    
        $this->pushCoreModels([$customer], true);
    }
}*/
