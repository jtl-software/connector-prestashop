<?php
namespace jtl\Connector\Presta\Controller;

use jtl\Connector\Presta\Utils\Utils;

class CategoryI18n extends BaseController
{	
	public function pullData($data, $model)
	{
		$result = $this->db->executeS('
			SELECT c.*
			FROM '._DB_PREFIX_.'category_lang c
			WHERE c.id_category = '.$data['id_category']
		);

		$return = array();

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
			$model->description[$id] = $i18n->getDescription();
			$model->link_rewrite[$id] = $i18n->getUrlPath();
			$model->meta_title[$id] = $i18n->getTitleTag();
			$model->meta_keywords[$id] = $i18n->getMetaKeywords();
			$model->meta_description[$id] = $i18n->getMetaDescription();
		}
	}
}
