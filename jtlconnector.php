<?php
if (!defined('_PS_VERSION_'))
    exit;

class JTLConnector extends Module
{
    public function __construct()
    {
        $this->name = 'jtlconnector';
        $this->tab = 'payments_gateways';
        $this->version = file_get_contents(__DIR__.'/version');
        $this->author = 'JTL Software GmbH';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = 'JTL Wawi Connector';
        $this->description = $this->l('This module enables a connection between PrestaShop and JTL Wawi.');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        $dbFile = __DIR__.DIRECTORY_SEPARATOR.'db'.DIRECTORY_SEPARATOR.'connector.s3db';
        chmod($dbFile, 0777);
        if (!is_writable($dbFile)) {
            $this->_errors[] = sprintf($this->l('The file "%s" must be writable.') , $dbFile);
        }

        $logDir = __DIR__.DIRECTORY_SEPARATOR.'logs';
        chmod($logDir, 0777);
        if (!is_writable($logDir)) {
            $this->_errors[] = sprintf($this->l('The directory "%s" must be writable.') , $logDir);
        }

        if (count($this->_errors) != 0) {
            return false;
        }

        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        $meta = new \Meta();

        $meta->page = 'module-jtlconnector-api';
        $meta->url_rewrite = array(1 => 'jtlconnector');
        $meta->configurable = '0';
        $meta->multilang = false;

        $meta->save();

        Db::getInstance()->Execute('
            CREATE TABLE IF NOT EXISTS jtl_connector_link (
              endpointId char(16) NOT NULL,
              hostId int(16) NOT NULL,
              type int(8),
              PRIMARY KEY (endpointId, hostId, type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci
        ');

        return parent::install() && Configuration::updateValue('jtlconnector_pass', uniqid());
    }

    public function uninstall()
    {
        $meta = \Meta::getMetaByPage('module-jtlconnector-api', 1);

        if (isset($meta['id_meta'])) {
            $delMeta = new \Meta($meta['id_meta']);
            $delMeta->delete();
        }

        return parent::uninstall() && Configuration::deleteByName('jtlconnector_pass');
    }

    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit'.$this->name))
        {
            $pass = strval(Tools::getValue('jtlconnector_pass'));
            if (!$pass  || empty($pass) || !Validate::isPasswd($pass, 8))
                $output .= $this->displayError($this->l('Password must have a minimum length of 8 chars!'));
            else
            {
                Configuration::updateValue('jtlconnector_pass', $pass);
                $output .= $this->displayConfirmation($this->l('Settings saved.'));
            }
        }

        return $output.$this->displayForm();
    }

    public function displayForm()
    {
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Connector Settings'),
                'icon' => 'icon-cogs'
            ),
            'description' => $this->l('Please enter the following URL in your Wawi connector setup:').'<br/><b>'.$this->context->link->getModuleLink('jtlconnector', 'api').'</b><br/>',
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('Password'),
                    'name' => 'jtlconnector_pass',
                    'size' => 10,
                    'required' => true
                )
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'button'
            )
        );

        $helper = new HelperForm();

        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submit'.$this->name;
        $helper->toolbar_btn = array(
            'save' =>
                array(
                    'desc' => $this->l('Save'),
                    'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                        '&token='.Tools::getAdminTokenLite('AdminModules'),
                ),
            'back' => array(
                'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        $helper->fields_value['jtlconnector_pass'] = Configuration::get('jtlconnector_pass');

        return $helper->generateForm($fields_form);
    }
}
