<?php

namespace jtl\Connector\Presta\Mapper;

class ProductVariation extends BaseMapper
{
    protected $pull = [
        'id'        => 'id',
        'sort'      => 'sort',
        'productId' => 'productId',
        'i18ns'     => 'ProductVariationI18n',
        'values'    => 'ProductVariationValue',
    ];
}
