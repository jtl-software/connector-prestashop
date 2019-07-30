<?php
namespace jtl\Connector\Presta\Controller;

use jtl\Connector\Model\ConnectorServerInfo;
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
            $class = "\\jtl\\Connector\\Presta\\Controller\\{$controller}";

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

        $returnMegaBytes = function($value) {
            $value = trim($value);
            $unit = strtolower($value[strlen($value) - 1]);
            switch ($unit) {
                case 'g':
                    $value *= 1024;
            }

            return (int) $value;
        };

        $serverInfo = new ConnectorServerInfo();
        $serverInfo->setMemoryLimit($returnMegaBytes(ini_get('memory_limit')))
            ->setExecutionTime((int) ini_get('max_execution_time'))
            ->setPostMaxSize($returnMegaBytes(ini_get('post_max_size')))
            ->setUploadMaxFilesize($returnMegaBytes(ini_get('upload_max_filesize')));

        $connector = new ConnectorIdentification();
        try{
            $version = \Symfony\Component\Yaml\Yaml::parseFile(CONNECTOR_DIR . '/build-config.yaml')['version'];
        }catch (\Exception $e){
            $version = 'Unknown';
        }
        
        $connector->setEndpointVersion($version)
            ->setPlatformName('PrestaShop')
            ->setPlatformVersion(_PS_VERSION_)
            ->setProtocolVersion(Application()->getProtocolVersion())
            ->setServerInfo($serverInfo);

        $action->setResult($connector);

        return $action;
    }
}
