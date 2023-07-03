<?php

namespace jtl\Connector\Presta\Mapper;

use jtl\Connector\Presta\Utils\Utils;

class ProductI18n extends BaseMapper
{
    protected $pull = [
        'productId'        => 'id_product',
        'description'      => 'description',
        'languageISO'      => null,
        'metaDescription'  => 'meta_description',
        'metaKeywords'     => 'meta_keywords',
        'name'             => 'name',
        'titleTag'         => 'meta_title',
        'urlPath'          => 'link_rewrite',
        'deliveryStatus'   => 'available_now',
        'shortDescription' => 'description_short'
    ];

    protected function languageISO($data)
    {
        return Utils::getInstance()->getLanguageIsoById($data['id_lang']);
    }
}
