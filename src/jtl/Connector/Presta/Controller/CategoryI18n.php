<?php
namespace jtl\Connector\Presta\Controller;

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

	}
}
