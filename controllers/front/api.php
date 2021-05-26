<?php

use jtl\Connector\Application\Application;
use jtl\Connector\Presta\Presta;

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
        if (isset($_SESSION)) {
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
