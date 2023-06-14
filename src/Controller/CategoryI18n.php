<?php

namespace jtl\Connector\Presta\Controller;

use jtl\Connector\Presta\Utils\Utils;

class CategoryI18n extends BaseController
{
    public function pullData($data, $model)
    {
        $result = $this->db->executeS(
            \sprintf('SELECT cl.*
			FROM ' . \_DB_PREFIX_ . 'category_lang cl
			LEFT JOIN ' . \_DB_PREFIX_ . 'lang AS l ON l.id_lang = cl.id_lang
			WHERE cl.id_category = %s AND cl.id_shop = %s', $data['id_category'], \Context::getContext()->shop->id)
        );

        $return = [];

        foreach ($result as $data) {
            $model = $this->mapper->toHost($data);

            $return[] = $model;
        }

        return $return;
    }

    public function pushData($data, $model)
    {
        foreach ($data->getI18ns() as $i18n) {
            $id = Utils::getInstance()->getLanguageIdByIso($i18n->getLanguageISO());

            $model->name[$id]             = $i18n->getName();
            $model->description[$id]      = Utils::cleanHtml($i18n->getDescription());
            $path                         = $i18n->getUrlPath();
            $model->link_rewrite[$id]     = \Tools::str2url(empty($path) ? $i18n->getName() : $path);
            $model->meta_title[$id]       = $i18n->getTitleTag();
            $model->meta_keywords[$id]    = $i18n->getMetaKeywords();
            $model->meta_description[$id] = $i18n->getMetaDescription();
        }
    }
}
