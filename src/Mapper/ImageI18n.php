<?php

namespace jtl\Connector\Presta\Mapper;

use jtl\Connector\Presta\Utils\Utils;

class ImageI18n extends BaseMapper
{
    protected $pull = [
        'altText'     => 'altText',
        'languageISO' => null,
    ];

    protected function languageISO($data)
    {
        return Utils::getInstance()->getLanguageIsoById($data['id_lang']);
    }
}
