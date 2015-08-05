<?php
namespace jtl\Connector\Presta\Mapper;

class Payment extends BaseMapper
{
    protected $pull = array(
        'id' => 'id_order_payment',
        'customerOrderId' => 'id_order',
        'creationDate' => 'date_add',
        'paymentModuleCode' => 'payment_method',
        'totalSum' => 'amount',
        'transactionId' => 'transaction_id'
    );
}
