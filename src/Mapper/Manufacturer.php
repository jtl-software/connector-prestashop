<?php

namespace jtl\Connector\Presta\Mapper;

class Manufacturer extends BaseMapper
{
    protected ?string $endpointModel = '\Manufacturer';
    protected string $identity       = 'id|id_manufacturer';

    protected array $pull = [
        'id'    => 'id_manufacturer',
        'name'  => 'name',
        'i18ns' => 'ManufacturerI18n'
    ];

    protected array $push = [
        'id'               => 'id',
        'id_manufacturer'  => 'id',
        'name'             => 'name',
        'ManufacturerI18n' => 'i18ns',
        'active'           => null
    ];

    protected function active($data): int
    {
        return 1;
    }
}
