<?php

namespace jtl\Connector\Presta\Controller;

use jtl\Connector\Presta\Utils\Utils;

class CategoryI18n extends BaseController
{
    public function pullData($data, $model)
    {
        $result = $this->db->executeS(
            '
			SELECT c.*
			FROM '._DB_PREFIX_.'category_lang c
			WHERE c.id_category = '.$data['id_category']
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

            $model->name[$id] = $i18n->getName();
            $model->description[$id] = Utils::cleanHtml($i18n->getDescription());
            $path = $i18n->getUrlPath();
            $model->link_rewrite[$id] = \Tools::str2url(empty($path) ? $i18n->getName() : $path);
            $model->meta_title[$id] = $i18n->getTitleTag();
            $model->meta_keywords[$id] = $i18n->getMetaKeywords();
            $model->meta_description[$id] = $i18n->getMetaDescription();
        }
    }
}
