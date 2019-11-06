<?php
namespace jtl\Connector\Presta\Mapper;

use jtl\Connector\Presta\Utils\Utils;

class ProductAttrI18n extends BaseMapper
{
    protected $pull = array(
        'productAttrId' => 'id_feature',
        'languageISO' => null,
        'name' => 'name',
        'value' => 'value'
    );

    protected function languageISO($data)
    {
        return Utils::getInstance()->getLanguageIsoById($data['id_lang']);
    }
}
