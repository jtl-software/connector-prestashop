<?php

namespace jtl\Connector\Presta\Mapper;

class TaxRate extends BaseMapper
{
    protected array $pull = [
        'id'   => 'id_tax',
        'rate' => 'rate'
    ];
}
