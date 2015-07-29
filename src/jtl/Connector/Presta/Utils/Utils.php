<?php
namespace jtl\Connector\Presta\Utils;

use \jtl\Connector\Session\SessionHelper;
use \jtl\Connector\Core\Utilities\Language;

class Utils {
	private static $instance;
	private $session = null;
	
	public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    public function __construct()
    {
    	$this->session = new SessionHelper("prestaConnector");
    }

	public function getLanguages()
	{
		if (is_null($this->session->languages)) {
			$languages = \Language::getLanguages();

			foreach ($languages as &$lang) {
				$lang['iso3'] = Language::convert($lang['iso_code']);
			}

			$this->session->languages = $languages;
		}

		return $this->session->languages;
	}

	public function getLanguageIdByIso($iso)
	{
		foreach ($this->getLanguages() as $lang) {
			if ($lang['iso3'] === $iso) {
				return $lang['id_lang'];
			}
		}

		return false;
	}

    public function getLanguageIsoById($id)
    {
        foreach ($this->getLanguages() as $lang) {
            if ($lang['id_lang'] === $id) {
                return $lang['iso3'];
            }
        }

        return false;
    }
}
