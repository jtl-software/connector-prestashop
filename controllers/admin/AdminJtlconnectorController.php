<?php

/**
 * Admin controller for JTL Connector tab.
 * Redirects to the module configuration page.
 *
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 * @phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminJtlconnectorController extends ModuleAdminController
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    /**
     * Initialize content - redirects to module configuration
     *
     * @return void
     */
    public function initContent(): void
    {
        $context = Context::getContext();
        if ($context !== null && $context->link !== null) {
            Tools::redirectAdmin($context->link->getAdminLink('AdminModules') . '&configure=jtlconnector');
        }
    }
}
