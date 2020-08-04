<?php

namespace jtl\Connector\Presta\Controller;

use \jtl\Connector\Model\ManufacturerI18n as ManufacturerI18nModel;
use jtl\Connector\Presta\Utils\Utils;

class ManufacturerI18n extends BaseController
{
    public function pullData($data, $model)
    {
        $manufacturer = new \Manufacturer($data['id_manufacturer']);

        $i18ns = [];

        foreach (Utils::getInstance()->getLanguages() as $language) {
            $i18n = new ManufacturerI18nModel();

            $i18n->setLanguageISO($language['iso3']);
            $i18n->setManufacturerId($model->getId());
            $i18n->setDescription($manufacturer->description[$language['id_lang']]);
            $i18n->setMetaDescription($manufacturer->meta_description[$language['id_lang']]);
            $i18n->setMetaKeywords($manufacturer->meta_keywords[$language['id_lang']]);
            $i18n->setTitleTag($manufacturer->meta_title[$language['id_lang']]);

            $i18ns[] = $i18n;
        }

        return $i18ns;
    }

    public function pushData($data, $model)
    {
        foreach ($data->getI18ns() as $i18n) {
            $id = Utils::getInstance()->getLanguageIdByIso($i18n->getLanguageISO());

            $model->description[$id] = \Tools::htmlentitiesUTF8($i18n->getDescription());
            $model->meta_title[$id] = $i18n->getTitleTag();
            $model->meta_keywords[$id] = $i18n->getMetaKeywords();
            $model->meta_description[$id] = $i18n->getMetaDescription();
        }
    }
}
