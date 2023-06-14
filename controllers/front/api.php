<?php

//phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

use jtl\Connector\Application\Application;
use jtl\Connector\Presta\Presta;

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

        $connector = Presta::getInstance();
        /** @var Application $application */
        $application = Application::getInstance();
        $application->createFeaturesFileIfNecessary(sprintf('%s/config/features.example.json', CONNECTOR_DIR));
        $application->register($connector);
        $application->run();

        exit();
    }
}
