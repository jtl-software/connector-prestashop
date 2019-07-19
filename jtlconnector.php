<?php
/**
 * JTL Connector Module
 * Copyright (c) 2015-2016 JTL Software GmbH
 *
 * @author    JTL Software GmbH
 * @copyright 2015-2016 JTL Software GmbH
 * @license   http://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License, version 3.0 (LGPL-3.0)
 * Description:
 * JTL Connector Module
 */

if (!defined('CONNECTOR_DIR')) {
    define("CONNECTOR_DIR", _PS_MODULE_DIR_ . 'jtlconnector/');
}

use jtl\Connector\Presta\Utils\Config;

class JTLConnector extends Module
{
    public function __construct()
    {
        if (file_exists(CONNECTOR_DIR . '/library/autoload.php')) {
            $loader = require_once CONNECTOR_DIR . '/library/autoload.php';
        } else {
            $loader = include_once 'phar://' . CONNECTOR_DIR . '/connector.phar/library/autoload.php';
        }
        
        if ($loader instanceof \Composer\Autoload\ClassLoader) {
            $loader->add('', CONNECTOR_DIR . '/plugins');
        }
        
        $this->name = 'jtlconnector';
        $this->tab = 'payments_gateways';
        try {
            $this->version = \Symfony\Component\Yaml\Yaml::parseFile(__DIR__ . '/build-config.yaml')['version'];
        } catch (\Exception $e) {
            $this->version = 'Unknown';
        }
        $this->author = 'JTL Software GmbH';
        $this->need_instance = 0;
        $this->bootstrap = true;
        
        parent::__construct();
        
        $this->displayName = 'JTL-Connector';
        $this->description = $this->l('This module enables a connection between PrestaShop and JTL Wawi.');
        $this->ps_versions_compliancy = ['min' => '1.6', 'max' => _PS_VERSION_];
        $this->module_key = '488cd335118c56baab7259d5459cf3a3';
    }
    
    public function viewAccess()
    {
        Tools::redirectAdmin(Context::getContext()->link->getAdminLink('AdminModules') . '&configure=jtlconnector');
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
        
        $dbFile = CONNECTOR_DIR . 'db' . DIRECTORY_SEPARATOR . 'connector.s3db';
        chmod($dbFile, 0777);
        if (!is_writable($dbFile)) {
            $this->_errors[] = sprintf($this->l('The file "%s" must be writable.'), $dbFile);
        }
        
        $logDir = CONNECTOR_DIR . 'logs';
        chmod($logDir, 0777);
        if (!is_writable($logDir)) {
            $this->_errors[] = sprintf($this->l('The directory "%s" must be writable.'), $logDir);
        }
        
        if (count($this->_errors) != 0) {
            $this->_errors[] = '<b>' . sprintf($this->l(
                    'Please read the %s for requirements and setup instructions.'
                ), '<a href="http://guide.jtl-software.de/jtl/JTL-Connector">Connector Guide</a>') . '</b>';
            
            return false;
        }
        
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }
        
        $meta = new \Meta();
        
        $meta->page = 'module-jtlconnector-api';
        $meta->url_rewrite = [1 => 'jtlconnector'];
        $meta->configurable = '0';
        $meta->multilang = false;
        
        $meta->save();
        
        $this->createLinkingTables();
        
        $tab = new \Tab();
        $name = "JTL-Connector";
        $tab->id_parent = (int)Tab::getIdFromClassName('IMPROVE');
        foreach (\Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = $name;
        }
        $tab->active = true;
        $tab->position = 0;
        $tab->module = $this->name;
        $tab->class_name = "jtlconnector";
        $tab->save();
        
        return parent::install() && Configuration::updateValue('jtlconnector_pass', uniqid());
    }
    
    public function uninstall()
    {
        $meta = \Meta::getMetaByPage('module-jtlconnector-api', 1);
        
        $id_tab = (int)Tab::getIdFromClassName('jtlconnector');
        if ($id_tab) {
            $tab = new Tab($id_tab);
            $tab->delete();
        }
        
        if (isset($meta['id_meta'])) {
            $delMeta = new \Meta($meta['id_meta']);
            $delMeta->delete();
        }
        
        return parent::uninstall() && Configuration::deleteByName('jtlconnector_pass');
    }
    
    public function getContent()
    {
        $output = null;
        
        if (Tools::isSubmit('submit' . $this->name)) {
            if (Tools::getValue('jtlconnector_clear_logs')) {
                $this->clearLogs();
                $output .= $this->displayConfirmation($this->l('Logs have been cleared successfully!'));
            } elseif (Tools::getValue('jtlconnector_remove_inconsistency')) {
                Db::getInstance()->execute(sprintf('DELETE FROM %sfeature_lang WHERE id_lang NOT IN (SELECT id_lang FROM %slang)',
                        _DB_PREFIX_,
                        _DB_PREFIX_)
                );
                $affected = Db::getInstance()->Affected_Rows();
                Db::getInstance()->execute(sprintf('DELETE FROM %sfeature_value_lang WHERE id_lang NOT IN (SELECT id_lang FROM %slang)',
                        _DB_PREFIX_,
                        _DB_PREFIX_)
                );
                $affected += Db::getInstance()->Affected_Rows();
                
                $output .= $this->displayConfirmation(sprintf("%s: %s",
                    $this->l('Successfully cleaned inconsistent entries'),
                    $affected));
            } elseif (Tools::getValue('jtlconnector_download_logs')) {
                $this->downloadJTLLogs();
            } else {
                $pass = (string)Tools::getValue('jtlconnector_pass');
                if (!$pass || empty($pass) || !Validate::isPlaintextPassword($pass, 8)) {
                    $output .= $this->displayError($this->l('Password must have a minimum length of 8 chars!'));
                } else {
                    Configuration::updateValue('jtlconnector_pass', $pass);
                    Configuration::updateValue('jtlconnector_truncate_desc',
                        Tools::getValue('jtlconnector_truncate_desc'));
                    Configuration::updateValue('jtlconnector_custom_fields',
                        Tools::getValue('jtlconnector_custom_fields'));
                    Configuration::updateValue('jtlconnector_from_date', Tools::getValue('jtlconnector_from_date'));
                    Config::set('developer_logging', Tools::getValue('jtlconnector_developer_logging'));
                    
                    $output .= $this->displayConfirmation($this->l('Settings saved.'));
                }
            }
        }
        
        return $output . $this->displayForm();
    }
    
    private function clearLogs()
    {
        $logDir = CONNECTOR_DIR . 'logs';
        $zip_file = CONNECTOR_DIR . 'tmp/connector_logs.zip';
        
        if (file_exists($zip_file)) {
            unlink($zip_file);
        }
        
        $files = glob($logDir . '/*.log');
        
        foreach ($files as $file) {
            if (!is_dir($file)) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }
    
    private function downloadJTLLogs()
    {
        $logDir = CONNECTOR_DIR . 'logs';
        $zip_file = CONNECTOR_DIR . '/tmp/connector_logs.zip';
        
        $zip = new ZipArchive();
        $zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        
        $files = glob($logDir . '/*.log');
        
        $fileCounter = 0;
        foreach ($files as $file) {
            if (!is_dir($file)) {
                $relativePath = substr($file, strlen($logDir) + 1);
                
                $zip->addFile($file, $relativePath);
                $fileCounter++;
            }
        }
        
        $zip->close();
        
        if ($fileCounter > 0) {
            header('Content-type: application/zip');
            header('Content-Disposition: attachment; filename="logs.zip"');
            readfile($zip_file);
            exit();
            
        } else {
            header('Content-Type: application/json; charset=UTF-8');
            header('HTTP/1.1 451 Internal Server Booboo');
            die(json_encode(['message' => 'Keine Logs Vorhanden!', 'code' => 451]));
        }
    }
    
    public function displayForm()
    {
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        
        $limit = Configuration::get('PS_PRODUCT_SHORT_DESC_LIMIT');
        if ($limit <= 0) {
            $limit = 800;
        }
        
        $fields_form = [];
        $fields_form[0]['form'] = [
            'legend'      => [
                'title' => $this->l('Connector Settings'),
                'icon'  => 'icon-cogs',
            ],
            'description' => sprintf('<b>%s</b><br>%s: <b>%s</b><br/>',
                $this->l('Please enter the following URL in your Wawi connector setup:'),
                $this->l('The "Onlineshop URL" is'),
                $this->context->link->getModuleLink('jtlconnector', 'api')),
            'input'       => [
                [
                    'type'     => 'textbutton',
                    'label'    => $this->l('Password'),
                    'name'     => 'jtlconnector_pass',
                    'size'     => 5,
                    'required' => true,
                    'button'   => [
                        'label'      => '<i class="icon-paste"></i>',
                        'attributes' => [
                            'onclick' => 'document.getElementById("jtlconnector_pass").select();document.execCommand("copy");',
                        ],
                    ],
                ],
                [
                    'type'    => 'switch',
                    'label'   => $this->l('Truncate short description'),
                    'name'    => 'jtlconnector_truncate_desc',
                    'is_bool' => true,
                    'desc'    => sprintf($this->l('Enable this option to truncate too long short descriptions. Your current setting is %s chars. You can change this in your product preferences.'),
                        $limit),
                    'values'  => [
                        [
                            'id'    => 'active_on',
                            'value' => true,
                            'label' => $this->l('Enabled'),
                        ],
                        [
                            'id'    => 'active_off',
                            'value' => false,
                            'label' => $this->l('Disabled'),
                        ],
                    ],
                ],
                [
                    'type'    => 'switch',
                    'label'   => $this->l('Add custom fields as attributes'),
                    'name'    => 'jtlconnector_custom_fields',
                    'is_bool' => true,
                    'desc'    => $this->l('Enable this option to add the custom fields as product attributes.'),
                    'values'  => [
                        [
                            'id'    => 'active_on',
                            'value' => true,
                            'label' => $this->l('Enabled'),
                        ],
                        [
                            'id'    => 'active_off',
                            'value' => false,
                            'label' => $this->l('Disabled'),
                        ],
                    ],
                ],
                [
                    'type'     => 'date',
                    'label'    => $this->l('Date treshold'),
                    'name'     => 'jtlconnector_from_date',
                    'desc'     => $this->l('If this option is set, only orders are pulled that are newer then this date.'),
                    'size'     => 5,
                    'required' => false,
                ],
                [
                    'type'  => 'button',
                    'label' => $this->l('Remove inconsistant specifics from the database'),
                    'text'  => $this->l('Remove'),
                    'name'  => 'jtlconnector_remove_inconsistency',
                    'icon'  => 'delete',
                    'desc'  => sprintf($this->l('Use this button to remove inconsistency in your specifics table caused by missing languages.'),
                        $limit),
                ],
                [
                    'type'         => 'html',
                    'name'         => '',
                    'html_content' => '<hr>',
                ],
                [
                    'type'   => 'switch',
                    'label'  => $this->l('Enable Developer Logging'),
                    'name'   => 'jtlconnector_developer_logging',
                    'desc'   => sprintf($this->l('Use this setting to enable developer logging.'), $limit),
                    'values' => [
                        [
                            'id'    => 'active_on',
                            'value' => true,
                            'label' => $this->l('Enabled'),
                        ],
                        [
                            'id'    => 'active_off',
                            'value' => false,
                            'label' => $this->l('Disabled'),
                        ],
                    ],
                ],
                [
                    'type'  => 'button',
                    'label' => $this->l('Clear logs'),
                    'text'  => $this->l('Clear'),
                    'name'  => 'jtlconnector_clear_logs',
                    'icon'  => 'delete',
                    'desc'  => $this->l('Use this button to clear your dev logs.'),
                ],
                [
                    'type'  => 'button',
                    'label' => $this->l('Download logs'),
                    'text'  => $this->l('Download'),
                    'name'  => 'jtlconnector_download_logs',
                    'icon'  => 'download',
                    'desc'  => $this->l('Use this button to download your dev logs.'),
                ],
            ],
            'submit'      => [
                'title' => $this->l('Save'),
                'class' => 'button',
            ],
        ];
        
        $helper = new HelperForm();
        
        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        
        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;
        
        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submit' . $this->name;
        
        // Load current value
        $helper->fields_value['jtlconnector_pass'] = Configuration::get('jtlconnector_pass');
        $helper->fields_value['jtlconnector_truncate_desc'] = Configuration::get('jtlconnector_truncate_desc');
        $helper->fields_value['jtlconnector_custom_fields'] = Configuration::get('jtlconnector_custom_fields');
        $helper->fields_value['jtlconnector_from_date'] = Configuration::get('jtlconnector_from_date');
        $helper->fields_value['jtlconnector_remove_inconsistency'] = false;
        $helper->fields_value['jtlconnector_developer_logging'] = Config::get('developer_logging');
        
        return $helper->generateForm($fields_form);
    }
    
    private function createLinkingTables()
    {
        $db = Db::getInstance();
        
        $link = $db->getLink();
        
        if ($link instanceof \PDO) {
            $link->beginTransaction();
        } elseif ($link instanceof \mysqli) {
            $link->begin_transaction();
        }
        
        try {
            $types = [
                1    => 'category',
                2    => 'customer',
                4    => 'customer_order',
                8    => 'delivery_note',
                16   => 'image',
                32   => 'manufacturer',
                64   => 'product',
                128  => 'specific',
                256  => 'specific_value',
                512  => 'payment',
                1024 => 'crossselling',
                2048 => 'crossselling_group',
            ];
            
            $queryInt = 'CREATE TABLE IF NOT EXISTS %s (
                endpoint_id INT(10) NOT NULL,
                host_id INT(10) NOT NULL,
                PRIMARY KEY (endpoint_id),
                INDEX (host_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci';
            
            $queryChar = 'CREATE TABLE IF NOT EXISTS %s (
                endpoint_id varchar(255) NOT NULL,
                host_id INT(10) NOT NULL,
                PRIMARY KEY (endpoint_id),
                INDEX (host_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci';
            
            foreach ($types as $id => $name) {
                if ($id == 16 || $id == 64) {
                    $db->query(sprintf($queryChar, 'jtl_connector_link_' . $name))->execute();
                } else {
                    $db->query(sprintf($queryInt, 'jtl_connector_link_' . $name))->execute();
                }
            }
            
            $check = $db->executeS('SHOW TABLES LIKE "jtl_connector_link"');
            
            if (!empty($check)) {
                $existingTypes = $db->executeS('SELECT type FROM jtl_connector_link GROUP BY type');
                
                foreach ($existingTypes as $existingType) {
                    $typeId = (int)$existingType['type'];
                    $tableName = 'jtl_connector_link_' . $types[$typeId];
                    $db->query("INSERT INTO {$tableName} (host_id, endpoint_id)
                        SELECT hostId, endpointId FROM jtl_connector_link WHERE type = {$typeId}
                        ")->execute();
                }
                
                if (count($existingTypes) > 0) {
                    $db->query("RENAME TABLE jtl_connector_link TO jtl_connector_link_backup")->execute();
                }
            }
            
            \Db::getInstance()->getLink()->commit();
            
            return true;
        } catch (\Exception $e) {
            $link->rollback();
            throw $e;
        }
    }
}
