<?php

namespace jtl\Connector\Presta\Mapper;

class GlobalData extends BaseMapper
{
    protected $pull = [
        'languages'       => 'Language',
        'currencies'      => 'Currency',
        'taxRates'        => 'TaxRate',
        'customerGroups'  => 'CustomerGroup',
        'shippingMethods' => 'ShippingMethod'
    ];
}
