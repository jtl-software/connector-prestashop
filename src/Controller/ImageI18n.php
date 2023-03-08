<?php

namespace jtl\Connector\Presta\Controller;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class ImageI18n
 * @package jtl\Connector\Presta\Controller
 */
class ImageI18n extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $i18ns = [];

        if ($data['relationType'] === 'product') {
            $imageLang = $this->db->executeS(sprintf(
                'SELECT il.id_lang, il.legend as altText 
                         FROM ' . _DB_PREFIX_ . 'image_lang il
                         LEFT JOIN '._DB_PREFIX_.'lang AS l ON l.id_lang = il.id_lang
                         WHERE l.id_lang IS NOT NULL AND il.id_image = %s',
                $this->db->escape($data['id'])
            ));

            if (is_array($imageLang)) {
                foreach ($imageLang as $data) {
                    $model = $this->mapper->toHost($data);
                    $i18ns[] = $model;
                }
            }
        }
        return $i18ns;
    }
}
