<?php
namespace jtl\Connector\Presta\Controller;

class Currency extends BaseController
{	
	public function pullData($data, $model, $limit = null)
	{
		$return = array();

		foreach (\Currency::getCurrencies() as $data) {
			$model = $this->mapper->toHost($data);
			
			$return[] = $model;
		}

		return $return;
	}
}
