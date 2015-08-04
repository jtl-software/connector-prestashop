<?php
namespace jtl\Connector\Presta\Mapper;

class ProductVariationValue extends BaseMapper
{
    protected $pull = array(
        'id' => 'id',
        'sort' => 'sort',
        'productVariationId' => 'productVariationId',
        'i18ns' => 'ProductVariationValueI18n'
    );
}
