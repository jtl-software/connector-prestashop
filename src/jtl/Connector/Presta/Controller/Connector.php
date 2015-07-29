<?php
namespace jtl\Connector\Presta\Controller;

use \jtl\Connector\Result\Action;
use \jtl\Connector\Model\Statistic;
use \jtl\Connector\Core\Controller\Controller;
use \jtl\Connector\Core\Model\QueryFilter;
use \jtl\Connector\Model\ConnectorIdentification;
use \jtl\Connector\Core\Rpc\Error;

class Connector extends Controller
{
    public function statistic(QueryFilter $filter)
    {
        $action = new Action();
        $action->setHandled(true);

        $return = [];

        $mainControllers = array(
            'Category',
            'Customer',
            'CustomerOrder',
            'Image',
            'Product',
            'Manufacturer',
            'Payment'
        );

        foreach ($mainControllers as $controller) {
            $class = "\\jtl\\Connector\\Oxid\\Controller\\{$controller}";

            if (class_exists($class)) {
                try {
                    $controllerObj = new $class();
                    
                    $statModel = new Statistic();

                    $statModel->setAvailable(intval($controllerObj->getStats()));
                    $statModel->setControllerName(lcfirst($controller));
                    
                    $return[] = $statModel;
                } catch (\Exception $exc) {
                    $err = new Error();
                    $err->setCode($exc->getCode());
                    $err->setMessage($exc->getMessage());
                    $action->setError($err);
                }
            }
        }

        $action->setResult($return);

        return $action;
    }

    public function identify()
    {
        $action = new Action();
        $action->setHandled(true);

        $connector = new ConnectorIdentification();
        $connector->setEndpointVersion(file_get_contents(CONNECTOR_DIR.'/version'));
        $connector->setPlatformName('PrestaShop');
        $connector->setPlatformVersion(_PS_VERSION_);
        $connector->setProtocolVersion(Application()->getProtocolVersion());

        $action->setResult($connector);

        return $action;
    }
}
