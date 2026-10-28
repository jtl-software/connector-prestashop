<?php

//phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

use Jtl\Connector\Core\Application\Application;
use Jtl\Connector\Core\Config\ConfigSchema;
use Jtl\Connector\Core\Config\FileConfig;
use Jtl\Connector\Core\Exception\ApplicationException;
use Jtl\Connector\Core\Exception\ConfigException;
use Jtl\Connector\Core\Exception\LoggerException;
use jtl\Connector\Presta\Connector;
use jtl\Connector\Presta\Utils\Config;
use Psr\Log\LogLevel;

class JtlconnectorApiModuleFrontController extends ModuleFrontController
{
    /**
     * @return void
     * @throws ReflectionException
     * @throws Throwable
     * @throws ApplicationException
     * @throws ConfigException
     * @throws LoggerException
     */
    public function initContent(): void
    {
        // The bundled Connector Core 6 is parsed with PHP 8.3 syntax. Refuse early on older PHP instead of loading
        // its autoloader and hitting a fatal parse error (install() blocks this, but the endpoint is not gated by it).
        if (\PHP_VERSION_ID < 80300) {
            \http_response_code(500);
            exit('The JTL-Connector requires PHP 8.3 or higher.');
        }
        require_once __DIR__ . '/../../lib/autoload.php';

        if (session_status() === \PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        $connector    = new Connector();
        $configSchema = new ConfigSchema();
        $config       = new FileConfig(\sprintf('%s/config/config.json', CONNECTOR_DIR), $configSchema);
        $config->set(ConfigSchema::SERIALIZER_ENABLE_CACHE, false);
        if (Config::get(ConfigSchema::DEBUG) === true) {
            $config->set(ConfigSchema::DEBUG, true);
            $config->set(ConfigSchema::LOG_LEVEL, LogLevel::DEBUG);
        }
        $application = new Application(CONNECTOR_DIR, $config, $configSchema);
        try {
            $application->run($connector);
        } catch (\Exception $e) {
            die();
        }
        exit();
    }
}
