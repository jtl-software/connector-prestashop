<?php

namespace jtl\Connector\Presta\Mapper;

use jtl\Connector\Presta\Utils\Utils;

class CustomerOrder extends BaseMapper
{
    protected $pull = [
        'id' => 'id_order',
        'customerId' => 'id_customer',
        'billingAddress' => 'CustomerOrderBillingAddress',
        'creationDate' => 'date_add',
        'currencyIso' => 'currency',
        'languageISO' => null,
        'orderNumber' => 'order_number',
        'paymentDate' => 'invoice_date',
        'paymentModuleCode' => null,
        'shippingAddress' => 'CustomerOrderShippingAddress',
        'shippingDate' => 'delivery_date',
        'shippingInfo' => 'shipping_number',
        'shippingMethodName' => 'shippingMethod',
        'totalSum' => 'total_paid',
        'totalSumGross' => 'total_paid_tax_incl',
        'items' => 'CustomerOrderItem',
        'attributes' => 'CustomerOrderAttr',
        'note' => null
    ];

    /**
     * @param $data
     * @return mixed
     */
    protected function paymentModuleCode($data)
    {
        return Utils::mapPaymentModuleCode($data['module']) ?? $data['payment'];
    }

    /**
     * @param $data
     * @return false|mixed
     */
    protected function languageISO($data)
    {
        return Utils::getInstance()->getLanguageIsoById($data['id_lang']);
    }

    /**
     * @param $data
     * @return mixed|string
     */
    protected function note($data)
    {
        $result = $this->db->getRow(sprintf("SELECT GROUP_CONCAT(CONCAT(cm.date_add, ' - ', cm.message) SEPARATOR '\r\n') as messages 
        FROM "._DB_PREFIX_."customer_thread ct
        LEFT JOIN "._DB_PREFIX_."customer_message cm ON cm.id_customer_thread = ct.id_customer_thread
        WHERE ct.id_order = %s", $this->db->escape($data['id_order'])));

        return isset($result['messages']) ? $result['messages'] : '';
    }
}
