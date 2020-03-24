<?php
namespace jtl\Connector\Presta\Mapper;

use \jtl\Connector\Presta\Utils\Utils;

class CategoryI18n extends BaseMapper
{
    protected $pull = array(
        'categoryId' => 'id_category',
        'description' => 'description',
        'languageISO' => null,
        'metaDescription' => 'meta_description',
        'metaKeywords' => 'meta_keywords',
        'name' => 'name',
        'titleTag' => 'meta_title',
        'urlPath' => 'link_rewrite'
    );

    protected function languageISO($data)
    {
        return Utils::getInstance()->getLanguageIsoById($data['id_lang']);
    }
}
