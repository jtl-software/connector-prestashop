<?php

namespace jtl\Connector\Presta\Mapper;

class ShippingMethod extends BaseMapper
{
    protected $pull = [
        'id' => 'id_carrier',
        'name' => 'name'
    ];
}
