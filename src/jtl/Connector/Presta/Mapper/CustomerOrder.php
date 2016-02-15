<?php
namespace jtl\Connector\Presta\Mapper;

use jtl\Connector\Presta\Utils\Utils;

class CustomerOrder extends BaseMapper
{
    protected $pull = array(
        'id' => 'id_order',
        'customerId' => 'id_customer',
        'billingAddress' => 'CustomerOrderBillingAddress',
        'creationDate' => 'date_add',
        'currencyIso' => 'currency',
        'languageISO' => null,
        'orderNumber' => 'reference',
        'paymentDate' => 'invoice_date',
        'paymentModuleCode' => 'module',
        'shippingAddress' => 'CustomerOrderShippingAddress',
        'shippingDate' => 'delivery_date',
        'shippingInfo' => 'shipping_number',
        'shippingMethodName' => 'shippingMethod',
        'totalSum' => 'total_paid',
        'totalSumGross' => 'total_paid_tax_incl',
        'items' => 'CustomerOrderItem',
        'attributes' => 'CustomerOrderAttr'
    );

    protected function languageISO($data)
    {
        return Utils::getInstance()->getLanguageIsoById($data['id_lang']);
    }
}
