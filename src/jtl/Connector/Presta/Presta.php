<?php

namespace jtl\Connector\Presta;

use \jtl\Connector\Core\Rpc\RequestPacket;
use \jtl\Connector\Core\Utilities\RpcMethod;
use \jtl\Connector\Base\Connector as BaseConnector;
use \jtl\Connector\Presta\Mapper\PrimaryKeyMapper;
use \jtl\Connector\Result\Action;
use \jtl\Connector\Presta\Auth\TokenLoader;
use \jtl\Connector\Presta\Checksum\ChecksumLoader;

class Presta extends BaseConnector
{
    protected $controller;
    protected $action;
    
    public function initialize()
    {
        $this->setPrimaryKeyMapper(new PrimaryKeyMapper());
        $this->setTokenLoader(new TokenLoader());
        $this->setChecksumLoader(new ChecksumLoader());
    }
    
    public function canHandle()
    {
        $controller = RpcMethod::buildController($this->getMethod()->getController());
        $class = "\\jtl\\Connector\\Presta\\Controller\\{$controller}";
        
        if (class_exists($class)) {
            $this->controller = $class::getInstance();
            $this->action = RpcMethod::buildAction($this->getMethod()->getAction());
            
            return is_callable([$this->controller, $this->action]);
        }
        
        return false;
    }
    
    public function handle(RequestPacket $requestpacket)
    {
        $this->controller->setMethod($this->getMethod());
        
        $actionExceptions = [
            'pull',
            'statistic',
            'identify',
        ];
        
        $callExceptions = [
            //'image.push'
        ];
        
        if (!in_array($this->action, $actionExceptions) && !in_array($requestpacket->getMethod(), $callExceptions)) {
            if (!is_array($requestpacket->getParams())) {
                throw new \Exception('data is not an array');
            }
            
            $action = new Action();
            $results = [];
            
            if (method_exists($this->controller, 'initPush')) {
                $this->controller->initPush($requestpacket->getParams());
            }
            $link = \Db::getInstance()->getLink();
            $currentItem = reset($items = $requestpacket->getParams());
            try {
                if ($link instanceof \PDO) {
                    $link->beginTransaction();
                } elseif ($link instanceof \mysqli) {
                    $link->begin_transaction();
                }
                foreach ($requestpacket->getParams() as $param) {
                    $currentItem = $param;
                    $result = $this->controller->{$this->action}($param);
                    $results[] = $result->getResult();
                }
                \Db::getInstance()->getLink()->commit();
            } catch (\Exception $e) {
                \Db::getInstance()->getLink()->rollback();
                if (method_exists($currentItem, 'getId')) {
                    throw new \Exception('Host-Id: ' . $currentItem->getId(), 0, $e);
                }
            }
            
            if (method_exists($this->controller, 'finishPush')) {
                $this->controller->finishPush($requestpacket->getParams(), $results);
            }
            
            $action->setHandled(true)
                ->setResult($results)
                ->setError($result->getError());
            
            return $action;
        }
        
        return $this->controller->{$this->action}($requestpacket->getParams());
    }
}
