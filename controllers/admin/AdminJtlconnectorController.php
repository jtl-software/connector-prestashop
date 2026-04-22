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
        $sfRouter = $this->get('router');
        if ($sfRouter instanceof \Symfony\Component\Routing\RouterInterface) {
            $url = $sfRouter->generate('admin_module_configure_action', [
                'module_name' => 'jtlconnector',
            ]);
            Tools::redirectAdmin($url);
        }
    }
}
