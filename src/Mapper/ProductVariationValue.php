<?php

namespace jtl\Connector\Presta\Mapper;

class ProductVariationValue extends BaseMapper
{
    protected $pull = [
        'id'                 => 'id',
        'sort'               => 'sort',
        'ean'                => 'ean',
        'sku'                => 'sku',
        'productVariationId' => 'productVariationId',
        'i18ns'              => 'ProductVariationValueI18n',
    ];
}
