<?php
namespace jtl\Connector\Presta\Controller;

class Category extends BaseController
{	
	private static $idCache = array();

	public function pullData($data, $model, $limit = null)
	{
		$result = $this->db->executeS('
			SELECT c.* 
			FROM '._DB_PREFIX_.'category c
			LEFT JOIN jtl_connector_link l ON c.id_category = l.endpointId AND l.type = 1
            WHERE l.hostId IS NULL 
            ORDER BY c.nleft
            LIMIT '.$limit
        );

		$return = array();

		foreach ($result as $data) {
			$model = $this->mapper->toHost($data);

			$return[] = $model;
		}

		return $return;
	}

	public function pushData($data)
	{
		if (isset(static::$idCache[$data->getParentCategoryId()->getHost()])) {
            $data->getParentCategoryId()->setEndpoint(static::$idCache[$data->getParentCategoryId()->getHost()]);
        }

		$category = $this->mapper->toEndpoint($data);
		$category->save();

		$id = $category->id;

		$data->getId()->setEndpoint($id);

		static::$idCache[$data->getId()->getHost()] = $id;

		return $data;
	}

	public function deleteData($data)
	{
		$category = new \Category($data->getId()->getEndpoint());

		if (!$category->delete()) {
			throw new \Exception('Error deleting category with id: '.$data->getId()->getEndpoint());
		}

		return $data;
	}

	public function prePush($data)
	{
		$id = $data->getId()->getEndpoint();

		if (!empty($id)) {
			$this->db->execute('DELETE FROM oxcategory2attribute where OXOBJECTID="'.$id.'"');
		}		
	}

	public function getStats()
	{
		return $this->db->getValue('
			SELECT COUNT(*) 
			FROM '._DB_PREFIX_.'category c
			LEFT JOIN jtl_connector_link l ON c.id_category = l.endpointId AND l.type = 1
            WHERE l.hostId IS NULL
        ');
	}
}
