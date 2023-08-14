<?php

namespace jtl\Connector\Presta\Mapper;

use jtl\Connector\Presta\Utils\Utils;

class ProductVariationValueI18n extends BaseMapper
{
    protected array $pull = [
        'productVariationValueId' => 'id_attribute',
        'languageISO'             => null,
        'name'                    => 'name'
    ];

    protected function languageISO($data)
    {
        return Utils::getInstance()->getLanguageIsoById($data['id_lang']);
    }
}
