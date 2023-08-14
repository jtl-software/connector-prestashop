<?php

namespace jtl\Connector\Presta\Mapper;

class ProductVariation extends BaseMapper
{
    protected array $pull = [
        'id'        => 'id',
        'sort'      => 'sort',
        'productId' => 'productId',
        'i18ns'     => 'ProductVariationI18n',
        'values'    => 'ProductVariationValue',
    ];
}
