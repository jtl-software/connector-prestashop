<?php

namespace jtl\Connector\Presta\Mapper;

use jtl\Connector\Presta\Utils\Utils;

class ProductVariationI18n extends BaseMapper
{
    protected array $pull = [
        'productVariationId' => 'id_attribute_group',
        'languageISO'        => null,
        'name'               => 'name'
    ];

    protected function languageISO($data)
    {
        return Utils::getInstance()->getLanguageIsoById($data['id_lang']);
    }
}
