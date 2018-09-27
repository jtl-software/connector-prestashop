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

if (!defined('CONNECTOR_DIR')) {
    define("CONNECTOR_DIR", _PS_MODULE_DIR_.'jtlconnector/');
}

class JTLConnector extends Module
{
    public function __construct()
    {
        $this->name = 'jtlconnector';
        $this->tab = 'payments_gateways';
        try {
            $this->version = file_get_contents(__DIR__ . '/version');
        } catch (\Exception $e) {
            $this->version = 'Unknown';
        }
        $this->author = 'JTL Software GmbH';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = 'JTL Wawi Connector';
        $this->description = $this->l('This module enables a connection between PrestaShop and JTL Wawi.');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->module_key = '488cd335118c56baab7259d5459cf3a3';
    }

    public function install()
    {
        if (version_compare(PHP_VERSION, '5.6.4') < 0) {
            $this->_errors[] =
                sprintf($this->l('The Connector requires PHP 5.6.4. Your system is running PHP %s.'), PHP_VERSION);
        }

        if (!extension_loaded('sqlite3')) {
            $this->_errors[] = $this->l('The required SQLite3 php extension is not installed.');
        }

        $dbFile = CONNECTOR_DIR.'db'.DIRECTORY_SEPARATOR.'connector.s3db';
        chmod($dbFile, 0777);
        if (!is_writable($dbFile)) {
            $this->_errors[] = sprintf($this->l('The file "%s" must be writable.'), $dbFile);
        }

        $logDir = CONNECTOR_DIR.'logs';
        chmod($logDir, 0777);
        if (!is_writable($logDir)) {
            $this->_errors[] = sprintf($this->l('The directory "%s" must be writable.'), $logDir);
        }

        if (count($this->_errors) != 0) {
            $this->_errors[] = '<b>'.sprintf($this->l(
                'Please read the %s for requirements and setup instructions.'
            ), '<a href="http://guide.jtl-software.de/jtl/JTL-Connector">Connector Guide</a>').'</b>';

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

        if (count(Db::getInstance()->ExecuteS('SHOW INDEX FROM jtl_connector_link WHERE Key_name = "PRIMARY"')) > 0) {
            Db::getInstance()->Execute('ALTER TABLE jtl_connector_link DROP PRIMARY KEY');
        }

        if (count(Db::getInstance()->ExecuteS('
                SHOW INDEX FROM jtl_connector_link WHERE Key_name = "endpointId"
            ')) == 0) {
            Db::getInstance()->Execute('ALTER TABLE jtl_connector_link ADD INDEX(endpointId)');
        }

        if (count(Db::getInstance()->ExecuteS('SHOW INDEX FROM jtl_connector_link WHERE Key_name = "hostId"')) == 0) {
            Db::getInstance()->Execute('ALTER TABLE jtl_connector_link ADD INDEX(hostId)');
        }
        if (count(Db::getInstance()->ExecuteS('SHOW INDEX FROM jtl_connector_link WHERE Key_name = "type"')) == 0) {
            Db::getInstance()->Execute('ALTER TABLE jtl_connector_link ADD INDEX(type)');
        }

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

        if (Tools::isSubmit('submit'.$this->name)) {
            $pass = (string) Tools::getValue('jtlconnector_pass');
            if (!$pass  || empty($pass) || !Validate::isPasswd($pass, 8)) {
                $output .= $this->displayError($this->l('Password must have a minimum length of 8 chars!'));
            } else {
                Configuration::updateValue('jtlconnector_pass', $pass);
                Configuration::updateValue('jtlconnector_truncate_desc', Tools::getValue('jtlconnector_truncate_desc'));
                Configuration::updateValue('jtlconnector_custom_fields', Tools::getValue('jtlconnector_custom_fields'));
                Configuration::updateValue('jtlconnector_from_date', Tools::getValue('jtlconnector_from_date'));
                $output .= $this->displayConfirmation($this->l('Settings saved.'));
            }
        }

        return $output.$this->displayForm();
    }

    public function displayForm()
    {
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        $limit = Configuration::get('PS_PRODUCT_SHORT_DESC_LIMIT');
        if ($limit <= 0) {
            $limit = 800;
        }

        $fields_form = array();
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Connector Settings'),
                'icon' => 'icon-cogs'
            ),
            'description' => $this->l('Please enter the following URL in your Wawi connector setup:').
                '<br/><b>'.$this->context->link->getModuleLink('jtlconnector', 'api').'</b><br/>',
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('Password'),
                    'name' => 'jtlconnector_pass',
                    'size' => 10,
                    'required' => true
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Truncate short description'),
                    'name' => 'jtlconnector_truncate_desc',
                    'is_bool' => true,
                    'desc' => sprintf($this->l('Enable this option to truncate too long short descriptions. Your current setting is %s chars. You can change this in your product preferences.'), $limit),
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => true,
                            'label' => $this->l('Enabled')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => false,
                            'label' => $this->l('Disabled')
                        )
                    ),
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Add custom fields as attributes'),
                    'name' => 'jtlconnector_custom_fields',
                    'is_bool' => true,
                    'desc' => sprintf($this->l('Enable this option to add the custom fields as product attributes.'), $limit),
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => true,
                            'label' => $this->l('Enabled')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => false,
                            'label' => $this->l('Disabled')
                        )
                    ),
                ),
                array(
                    'type' => 'date',
                    'label' => $this->l('Date treshold'),
                    'name' => 'jtlconnector_from_date',
                    'desc' => $this->l('If this option is set, only orders are pulled that are newer then this date.'),
                    'size' => 5,
                    'required' => false
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
        $helper->fields_value['jtlconnector_truncate_desc'] = Configuration::get('jtlconnector_truncate_desc');
        $helper->fields_value['jtlconnector_custom_fields'] = Configuration::get('jtlconnector_custom_fields');
        $helper->fields_value['jtlconnector_from_date'] = Configuration::get('jtlconnector_from_date');

        return $helper->generateForm($fields_form);
    }
}
