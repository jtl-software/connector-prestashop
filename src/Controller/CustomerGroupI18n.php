<?php

namespace jtl\Connector\Presta\Controller;

use \jtl\Connector\Model\CustomerGroupI18n as CustomerGroupI18nModel;
use jtl\Connector\Presta\Utils\Utils;

class CustomerGroupI18n extends BaseController
{
    public function pullData($data, $model)
    {
        $group = new \Group($data['id_group']);

        $i18ns = [];

        foreach (Utils::getInstance()->getLanguages() as $language) {
            if (isset($group->name[$language['id_lang']])) {
                $i18n = new CustomerGroupI18nModel();
                $i18n->setName($group->name[$language['id_lang']]);
                $i18n->setLanguageISO($language['iso3']);
                $i18n->setCustomerGroupId($model->getId());

                $i18ns[] = $i18n;
            }
        }

        return $i18ns;
    }
}
