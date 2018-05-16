<?php
namespace jtl\Connector\Presta\Mapper;

use \jtl\Connector\Model\Identity;

class BaseMapper
{
	protected $db = null;
	private $model = null;
	private $type;
    protected $endpointModel = null;

	public function __construct()
	{
		$reflect = new \ReflectionClass($this);
		$typeClass = "\\jtl\\Connector\\Type\\{$reflect->getShortName()}";

		$this->db = \DB::getInstance();
		$this->model = "\\jtl\\Connector\\Model\\{$reflect->getShortName()}";
        $this->type = new $typeClass();
	}

	public function toHost($data)
	{
		$model = new $this->model();

		foreach ($this->pull as $host => $endpoint) {
			$setter = 'set'.ucfirst($host);			
			$fnName = strtolower($host);

			if (method_exists($this, $fnName)) {
				$value = $this->$fnName($data);
			} else {
				$value = isset($data[$endpoint]) ? $data[$endpoint] : null;
				$property = $this->type->getProperty($host);

				if ($property->isNavigation()) {
					$subControllerName = "\\jtl\\Connector\\Presta\\Controller\\".$endpoint;
					
					if (class_exists($subControllerName)) {
						$subController = new $subControllerName();
						$value = $subController->pullData($data, $model);
					}
				} elseif ($property->isIdentity()) {
					$value = new Identity($value);
				} elseif ($property->getType() == 'boolean') {
					$value = (bool) $value;
				} elseif ($property->getType() == 'integer') {
					$value = intval($value);
				} elseif ($property->getType() == 'double') {
					$value = floatval($value);
				} elseif ($property->getType() == 'DateTime') {
					$value = $value == '0000-00-00' || $value == '0000-00-00 00:00:00' ? null : new \DateTime($value);
				}
			}		

			if (!empty($value)) $model->$setter($value);
		}

		return $model;
	}

	public function toEndpoint($data, $customData = null)
	{
		$id = null;

        if (isset($this->identity)) {
            list($hostField, $endpointField) = explode('|', $this->identity);
            $endpointId = $data->{'get'.ucfirst($hostField)}()->getEndpoint();
            if (!empty($endpointId)) {
                $id = $endpointId;
            }
        }

        $model = new $this->endpointModel($id);

		foreach ($this->push as $endpoint => $host) {
			$fnName = strtolower($endpoint);

			if (method_exists($this, $fnName)) {
				$value = $this->$fnName($data, $customData);
			} else {
				$getter = 'get'.ucfirst($host);

				$value = $data->$getter();
				$property = $this->type->getProperty($host);

				if ($property->isNavigation()) {
					$subControllerName = "\\jtl\\Connector\\Presta\\Controller\\".$endpoint;
					
					if (class_exists($subControllerName)) {
						$subController = new $subControllerName();
						$subController->pushData($data, $model);
					}
				} elseif ($property->isIdentity()) {
					$value = $value->getEndpoint();
				} elseif ($property->getType() == 'DateTime') {
					$value = $value === null ? '0000-00-00 00:00:00' : $value->format('Y-m-d H:i:s');
				}
			}

            $model->$endpoint = $value;
		}

		return $model;
	}
}
