<?php

class jtlconnectorApiModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        defined('CONNECTOR_DIR') || define("CONNECTOR_DIR", realpath(__DIR__.'/../../'));

        if (file_exists(CONNECTOR_DIR.'/vendor/autoload.php')) {
            require_once CONNECTOR_DIR.'/vendor/autoload.php';
        } else {
            include_once 'phar://'.CONNECTOR_DIR.'/connector.phar';
        }

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