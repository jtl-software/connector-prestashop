<?php

namespace jtl\Connector\Presta\Mapper;

class Manufacturer extends BaseMapper
{
    protected $endpointModel = '\Manufacturer';
    protected $identity      = 'id|id_manufacturer';

    protected $pull = [
        'id'    => 'id_manufacturer',
        'name'  => 'name',
        'i18ns' => 'ManufacturerI18n'
    ];

    protected $push = [
        'id'               => 'id',
        'id_manufacturer'  => 'id',
        'name'             => 'name',
        'ManufacturerI18n' => 'i18ns',
        'active'           => null
    ];

    protected function active($data)
    {
        return 1;
    }
}
