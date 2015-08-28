<?php
namespace jtl\Connector\Presta;

use \jtl\Connector\Core\Rpc\RequestPacket;
use \jtl\Connector\Core\Utilities\RpcMethod;
use \jtl\Connector\Core\Rpc\ResponsePacket;
use \jtl\Connector\Session\SessionHelper;
use \jtl\Connector\Base\Connector as BaseConnector;
use \jtl\Connector\Core\Rpc\Error as Error;
use \jtl\Connector\Core\Http\Response;
use \jtl\Connector\Presta\Mapper\PrimaryKeyMapper;
use \jtl\Connector\Core\Config\Config;
use \jtl\Connector\Core\Config\Loader\System as ConfigSystem;
use \jtl\Connector\Result\Action;
use \jtl\Connector\Presta\Auth\TokenLoader;
use \jtl\Connector\Presta\Checksum\ChecksumLoader;
use \jtl\Connector\Core\Logger\Logger;

class Presta extends BaseConnector
{
    protected $controller;
    protected $action;

    public function initialize()
    {
        $this->initConnectorConfig();

        set_error_handler(array($this,'errorHandler'), E_ALL);
        set_exception_handler(array($this,'exceptionHandler'));
        register_shutdown_function(array($this,'shutdownHandler'));

        $this->setPrimaryKeyMapper(new PrimaryKeyMapper());
        $this->setTokenLoader(new TokenLoader());
        $this->setChecksumLoader(new ChecksumLoader());
    }

    protected function initConnectorConfig()
    {
        $session = new SessionHelper("prestaConnector");

        $config = null;

        if (isset($session->config)) {
            $config = $session->config;
        }

        if (empty($config)) {
            if (!is_null($this->config)) {
                $config = $this->getConfig();
            }

            if (empty($config)) {
                $config = new Config(array(
                    new ConfigSystem()
                ));

                $this->setConfig($config);
            }
        }

        if (!isset($session->config)) {
            $session->config = $config;
        }
    }

    public function canHandle()
    {
        $controller = RpcMethod::buildController($this->getMethod()->getController());
        $class = "\\jtl\\Connector\\Presta\\Controller\\{$controller}";

        if (class_exists($class)) {
            $this->controller = $class::getInstance();
            $this->action = RpcMethod::buildAction($this->getMethod()->getAction());

            return is_callable(array($this->controller, $this->action));
        }

        return false;
    }

    public function handle(RequestPacket $requestpacket)
    {
        $this->controller->setMethod($this->getMethod());

        $actionExceptions = array(
            'pull',
            'statistic',
            'identify'
        );

        $callExceptions = array(
            //'image.push'
        );

        if (!in_array($this->action, $actionExceptions) && !in_array($requestpacket->getMethod(), $callExceptions)) {
            if (!is_array($requestpacket->getParams())) {
                throw new \Exception('data is not an array');
            }

            $action = new Action();
            $results = array();

            if (method_exists($this->controller, 'initPush')) {
                $this->controller->initPush($requestpacket->getParams());
            }

            foreach ($requestpacket->getParams() as $param) {
                $result = $this->controller->{$this->action}($param);
                $results[] = $result->getResult();
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

    public function errorHandler($errno, $errstr, $errfile, $errline, $errcontext)
    {
        $types = array(
            E_ERROR => array(Logger::ERROR, 'E_ERROR'),
            E_WARNING => array(Logger::WARNING, 'E_WARNING'),
            E_PARSE => array(Logger::WARNING, 'E_PARSE'),
            E_NOTICE => array(Logger::NOTICE, 'E_NOTICE'),
            E_CORE_ERROR => array(Logger::ERROR, 'E_CORE_ERROR'),
            E_CORE_WARNING => array(Logger::WARNING, 'E_CORE_WARNING'),
            E_CORE_ERROR => array(Logger::ERROR, 'E_COMPILE_ERROR'),
            E_CORE_WARNING => array(Logger::WARNING, 'E_COMPILE_WARNING'),
            E_USER_ERROR => array(Logger::ERROR, 'E_USER_ERROR'),
            E_USER_WARNING => array(Logger::WARNING, 'E_USER_WARNING'),
            E_USER_NOTICE => array(Logger::NOTICE, 'E_USER_NOTICE'),
            E_STRICT => array(Logger::NOTICE, 'E_STRICT'),
            E_RECOVERABLE_ERROR => array(Logger::ERROR, 'E_RECOVERABLE_ERROR'),
            E_DEPRECATED => array(Logger::INFO, 'E_DEPRECATED'),
            E_USER_DEPRECATED => array(Logger::INFO, 'E_USER_DEPRECATED')
        );

        if (isset($types[$errno])) {
            $err = "(" . $types[$errno][1] . ") File ({$errfile}, {$errline}): {$errstr}";
            Logger::write($err, $types[$errno][0], 'global');
        } else {
            Logger::write("File ({$errfile}, {$errline}): {$errstr}", Logger::ERROR, 'global');
        }
    }

    public function exceptionHandler(\Exception $exception)
    {
        $trace = $exception->getTrace();
        if (isset($trace[0]['args'][0])) {
            $requestpacket = $trace[0]['args'][0];
        }

        $error = new Error();
        $error->setCode($exception->getCode())
            ->setData("Exception: " . substr(strrchr(get_class($exception), "\\"), 1) . " - File: {$exception->getFile()} - Line: {$exception->getLine()}")
            ->setMessage($exception->getMessage());

        $responsepacket = new ResponsePacket();
        $responsepacket->setError($error)
            ->setJtlrpc("2.0");

        if (isset($requestpacket) && $requestpacket !== null && is_object($requestpacket) && get_class($requestpacket) == "jtl\\Connector\\Core\\Rpc\\RequestPacket") {
            $responsepacket->setId($requestpacket->getId());
        }

        Response::send($responsepacket);
    }

    public function shutdownHandler()
    {
        if (($err = error_get_last())) {
            if ($err['type'] != 2 && $err['type'] != 8) {
                ob_clean();

                $error = new Error();
                $error->setCode($err['type'])
                    ->setData('Shutdown! File: ' . $err['file'] . ' - Line: ' . $err['line'])
                    ->setMessage($err['message']);

                $responsepacket = new ResponsePacket();
                $responsepacket->setError($error)
                    ->setId('unknown')
                    ->setJtlrpc("2.0");

                Response::send($responsepacket);
            }
        }
    }
}
