<?php

namespace jtl\Connector\Presta\Mapper;

class TaxRate extends BaseMapper
{
    protected $pull = [
        'id' => 'id_tax',
        'rate' => 'rate'
    ];
}
