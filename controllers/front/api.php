<?php

class jtlconnectorApiModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        require_once realpath(__DIR__ . "/../../vendor/autoload.php");

        defined('CONNECTOR_DIR') || define("CONNECTOR_DIR", realpath(__DIR__.'/../../'));

        $connector = \jtl\Connector\Presta\Presta::getInstance();

        try {
            $application = \jtl\Connector\Application\Application::getInstance();
            $application->register($connector);
            $application->run();
        } catch (\Exception $exc) {
            $connector->exceptionHandler($exc);
        }

        exit();
    }
}