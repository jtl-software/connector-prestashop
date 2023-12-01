<?php

//phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

use Jtl\Connector\Core\Application\Application;
use Jtl\Connector\Core\Config\ConfigSchema;
use Jtl\Connector\Core\Config\FileConfig;
use jtl\Connector\Presta\Connector;
use jtl\Connector\Presta\Utils\Config;
use Psr\Log\LogLevel;

require_once CONNECTOR_DIR . '/lib/autoload.php';

/**
 * JTL Connector Module
 *
 * Copyright (c) 2015-2016 JTL Software GmbH
 *
 * @author    JTL Software GmbH
 * @copyright 2015-2016 JTL Software GmbH
 * @license   http://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License, version 3.0 (LGPL-3.0)
 *
 * Description:
 *
 * JTL Connector Module
 */
class JtlconnectorApiModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        if (session_status() === \PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        $connector = new Connector();
        $configSchema = new ConfigSchema();
        $config       = new FileConfig(\sprintf('%s/config/config.json', CONNECTOR_DIR), $configSchema);
        $config->set(ConfigSchema::SERIALIZER_ENABLE_CACHE, false);
        $config->set(ConfigSchema::LOG_DIR, \sprintf('%s/logs', CONNECTOR_DIR));
        if (Config::get(ConfigSchema::DEBUG) === true) {
            $config->set(ConfigSchema::DEBUG, true);
            $config->set(ConfigSchema::LOG_LEVEL, LogLevel::DEBUG);
        }
        $application = new Application(CONNECTOR_DIR, $config);
        $application->run($connector);

        exit();
    }
}
