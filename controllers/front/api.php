<?php
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

        $connector = \jtl\Connector\Presta\Presta::getInstance();

        $application = \jtl\Connector\Application\Application::getInstance();
        $application->register($connector);
        $application->run();

        exit();
    }
}
