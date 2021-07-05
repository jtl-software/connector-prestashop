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
        'transactionId' => null
    ];

    /**
     * @param array $data
     * @return mixed
     */
    protected function paymentModuleCode(array $data): string
    {
        return Utils::mapPaymentModuleCode($data['module']) ?? $data['payment'];
    }

    /**
     * @param array $data
     * @return string
     */
    protected function transactionId(array $data): string
    {
        if ((string)$data['transaction_id'] !== '') {
            return $data['transaction_id'];
        }

        return $data['order_reference'];
    }
}
