<?php

namespace jtl\Connector\Presta\Mapper;

use jtl\Connector\Presta\Utils\Utils;

class Payment extends BaseMapper
{
    protected $pull = [
        'id' => 'id_order_payment',
        'customerOrderId' => 'id_order',
        'creationDate' => 'date_add',
        'paymentModuleCode' => null,
        'totalSum' => 'amount',
        'transactionId' => 'transaction_id'
    ];

    /**
     * @param $data
     * @return mixed
     */
    protected function paymentModuleCode($data)
    {
        return Utils::mapPaymentModuleCode($data['module']) ?? $data['payment'];
    }
}
