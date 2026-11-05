<?php

declare(strict_types=1);

//phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Admin controller of the module's back office menu entry (tab class name "AdminJtlconnector").
 *
 * PrestaShop 9 only opens a module's menu entry if the module ships an admin controller of that name; without it the
 * entry ends in "page not found". It forwards to the module's configuration page.
 */
//phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace
class AdminJtlconnectorController extends ModuleAdminController
{
    /**
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
        Tools::redirectAdmin($link->getAdminLink('AdminModules', true, [], ['configure' => 'jtlconnector']));
    }
}
