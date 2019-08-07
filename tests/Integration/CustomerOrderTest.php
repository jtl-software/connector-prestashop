<?php

namespace Tests\Integration;

use DateTime;
use jtl\Connector\Model\CustomerOrder;
use jtl\Connector\Model\CustomerOrderBillingAddress;
use jtl\Connector\Model\CustomerOrderItem;
use jtl\Connector\Model\CustomerOrderItemVariation;
use jtl\Connector\Model\CustomerOrderPaymentInfo;
use jtl\Connector\Model\CustomerOrderShippingAddress;
use jtl\Connector\Model\Identity;
use jtl\Connector\Model\CustomerOrderAttr;
use Tests\PrestashopConnectorTestCase;

/*class CustomerOrderTest extends PrestashopConnectorTestCase
{
    public function testCustomerOrderBasicPush()
    {
        $customerOrder = new CustomerOrder();
        $customerOrder->setCustomerId(new Identity('', 1));
        $customerOrder->setId(new Identity('', 1));
        $customerOrder->setCarrierName('');
        $customerOrder->setCreationDate(new DateTime());
        $customerOrder->setCurrencyIso('');
        $customerOrder->setEstimatedDeliveryDate(new DateTime());
        $customerOrder->setLanguageISO('ger');
        $customerOrder->setNote('');
        $customerOrder->setOrderNumber('');
        $customerOrder->setPaymentDate(new DateTime);
        $customerOrder->setPaymentModuleCode('');
        $customerOrder->setPaymentStatus('');
        $customerOrder->setPui('');
        $customerOrder->setShippingDate(new DateTime());
        $customerOrder->setShippingInfo('');
        $customerOrder->setShippingMethodId(new Identity('', 1));
        $customerOrder->setShippingMethodName('');
        $customerOrder->setStatus('');
        $customerOrder->setTotalSum(0.0);
        $customerOrder->setTotalSumGross(0.0);
        
        $this->pushCoreModels([$customerOrder], true);
    }
    
    public function testCustomerOrderBillingAddressPush()
    {
        $customerOrder = new CustomerOrder();
            $billingAddress = new CustomerOrderBillingAddress();
            $billingAddress->setCustomerId(new Identity('', 1));
            $billingAddress->setId(new Identity('', 1));
            $billingAddress->setCity('');
            $billingAddress->setCompany('');
            $billingAddress->setCountryIso('');
            $billingAddress->setDeliveryInstruction('');
            $billingAddress->setEMail('');
            $billingAddress->setExtraAddressLine('');
            $billingAddress->setFax('');
            $billingAddress->setFirstName('');
            $billingAddress->setLastName('');
            $billingAddress->setMobile('');
            $billingAddress->setPhone('');
            $billingAddress->setSalutation('');
            $billingAddress->setState('');
            $billingAddress->setStreet('');
            $billingAddress->setTitle('');
            $billingAddress->setVatNumber('');
            $billingAddress->setZipCode('');
        $customerOrder->setBillingAddress($billingAddress);
    
        $this->pushCoreModels([$customerOrder], true);
    }
    
    public function testCustomerOrderPaymentInfoPush()
    {
        $customerOrder = new CustomerOrder();
            $paymentInfo = new CustomerOrderPaymentInfo();
            $paymentInfo->setCustomerOrderId(new Identity('', 1));
            $paymentInfo->setId(new Identity('', 1));
            $paymentInfo->setAccountHolder('');
            $paymentInfo->setAccountNumber('');
            $paymentInfo->setBankCode('');
            $paymentInfo->setBankName('');
            $paymentInfo->setBic('');
            $paymentInfo->setCreditCardExpiration('');
            $paymentInfo->setCreditCardHolder('');
            $paymentInfo->setCreditCardNumber('');
            $paymentInfo->setCreditCardType('');
            $paymentInfo->setCreditCardVerificationNumber('');
            $paymentInfo->setIban('');
        $customerOrder->setPaymentInfo($paymentInfo);
    
        $this->pushCoreModels([$customerOrder], true);
    }
    
    public function testCustomerOrderShippingAddressPush()
    {
        $customerOrder = new CustomerOrder();
            $shippingAddress = new CustomerOrderShippingAddress();
            $shippingAddress->setCustomerId(new Identity('', 1));
            $shippingAddress->setId(new Identity('', 1));
            $shippingAddress->setCity('');
            $shippingAddress->setCompany('');
            $shippingAddress->setCountryIso('');
            $shippingAddress->setDeliveryInstruction('');
            $shippingAddress->setEMail('');
            $shippingAddress->setExtraAddressLine('');
            $shippingAddress->setFax('');
            $shippingAddress->setFirstName('');
            $shippingAddress->setLastName('');
            $shippingAddress->setMobile('');
            $shippingAddress->setPhone('');
            $shippingAddress->setSalutation('');
            $shippingAddress->setState('');
            $shippingAddress->setStreet('');
            $shippingAddress->setTitle('');
            $shippingAddress->setZipCode('');
        $customerOrder->setShippingAddress($shippingAddress);
        $this->pushCoreModels([$customerOrder], true);
    }
    
    public function testCustomerOrderAttributesPush()
    {
        $customerOrder = new CustomerOrder();
            $attribute = new CustomerOrderAttr();
            $attribute->setCustomerOrderId(new Identity('', 1));
            $attribute->setId(new Identity('', 1));
            $attribute->setKey('');
            $attribute->setValue('');
        $customerOrder->addAttribute($attribute);
    
        $this->pushCoreModels([$customerOrder], true);
    }
    
    public function testCustomerOrderItemsPush()
    {
        $customerOrder = new CustomerOrder();
            $item = new CustomerOrderItem();
            $item->setConfigItemId(new Identity('', 1));
            $item->setCustomerOrderId(new Identity('', 1));
            $item->setId(new Identity('', 1));
            $item->setProductId(new Identity('', 1));
            $item->setName('');
            $item->setPrice(0.0);
            $item->setPriceGross(0.0);
            $item->setQuantity(0.0);
            $item->setSku('');
            $item->setType('');
            $item->setNote('');
            $item->setUnique('');
            $item->setVat(0.0);
                $itemVariation = new CustomerOrderItemVariation();
                $itemVariation->setCustomerOrderItemId(new Identity('', 1));
                $itemVariation->setId(new Identity('', 1));
                $itemVariation->setProductVariationId(new Identity('', 1));
                $itemVariation->setProductVariationValueId(new Identity('', 1));
                $itemVariation->setFreeField('');
                $itemVariation->setProductVariationName('');
                $itemVariation->setSurcharge(0.0);
                $itemVariation->setValueName('');
            $item->addVariation($itemVariation);
        $customerOrder->addItem($item);
    
        $this->pushCoreModels([$customerOrder], true);
    }
}*/
