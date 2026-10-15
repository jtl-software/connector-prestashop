<?php

declare(strict_types=1);

/**
 * JTL Connector Module — admin tab controller.
 *
 * PrestaShop 9 LegacyRouterChecker requires a matching admin controller file
 * for any tab registered with a module reference. This controller exists to
 * satisfy that check and forwards the user to the module configuration page.
 *
 * @author    JTL Software GmbH
 * @copyright JTL Software GmbH
 * @license   http://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License, version 3.0 (LGPL-3.0)
 */

//phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace
class AdminJtlconnectorController extends ModuleAdminController
{
    /**
     * AdminJtlconnectorController constructor.
     *
     * @throws PrestaShopException
     */
    public function __construct()
    {
        parent::__construct();
        $this->bootstrap = true;
    }

    /**
     * @return void
     */
    public function initContent(): void
    {
        /** @var Link $link */
        $link = $this->context->link;
        Tools::redirectAdmin($link->getAdminLink('AdminModules') . '&configure=jtlconnector');
    }
}
