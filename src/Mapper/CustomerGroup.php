<?php

namespace jtl\Connector\Presta\Mapper;

class CustomerGroup extends BaseMapper
{
    protected $pull = [
        'id' => 'id_group',
        'isDefault' => null,
        'applyNetPrice' => 'price_display_method',
        'discount' => 'reduction',
        'i18ns' => 'CustomerGroupI18n'
    ];

    protected function isDefault($data)
    {
        return $data['id_group'] === '1' ? true : false;
    }
}
