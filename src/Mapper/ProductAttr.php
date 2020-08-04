<?php

namespace jtl\Connector\Presta\Mapper;

class ProductAttr extends BaseMapper
{
    protected $pull = [
        'id' => 'id_feature',
        'productId' => 'id_product',
        'i18ns' => 'ProductAttrI18n',
        'isTranslated' => null
    ];

    protected function isTranslated($data)
    {
        return true;
    }
}
