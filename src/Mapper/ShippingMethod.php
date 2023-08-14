<?php

namespace jtl\Connector\Presta\Mapper;

class ShippingMethod extends BaseMapper
{
    protected array $pull = [
        'id'   => 'id_carrier',
        'name' => 'name'
    ];
}
