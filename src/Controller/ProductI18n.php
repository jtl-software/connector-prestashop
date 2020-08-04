<?php

namespace jtl\Connector\Presta\Controller;

use jtl\Connector\Presta\Utils\Utils;

class ProductI18n extends BaseController
{
    public function pullData($data, $model, $limit = null)
    {
        $varNames = [];

        if ($model->getIsMasterProduct() !== true && count($model->getVariations()) > 0) {
            foreach ($model->getVariations() as $variation) {
                foreach ($variation->getValues() as $value) {
                    foreach ($value->getI18ns() as $i18n) {
                        $id = Utils::getInstance()->getLanguageIdByIso($i18n->getLanguageISO());

                        if (!is_null($id) && isset($varNames[$id])) {
                            $varNames[$id] .= ' ' . $i18n->getName();
                        }
                    }
                }
            }
        }

        $result = $this->db->executeS(
            '
			SELECT p.*
			FROM '._DB_PREFIX_.'product_lang p
			WHERE p.id_product = '.$data['id_product']
        );

        $return = [];

        foreach ($result as $data) {
            if (isset($varNames[$data['id_lang']])) {
                $data['name'] .= $varNames[$data['id_lang']];
            }

            $model = $this->mapper->toHost($data);

            $return[] = $model;
        }

        return $return;
    }

    public function pushData($data, $model)
    {
        $limit = null;

        if (\Configuration::get('jtlconnector_truncate_desc')) {
            $limit = (int) \Configuration::get('PS_PRODUCT_SHORT_DESC_LIMIT');
            if ($limit <= 0) {
                $limit = 800;
            }
        }

        foreach ($data->getI18ns() as $i18n) {
            $name = $i18n->getName();
            if (!empty($name)) {
                $id = Utils::getInstance()->getLanguageIdByIso($i18n->getLanguageISO());

                $model->name[$id] = str_replace('#', '', $i18n->getName());
                $model->description[$id] = $this->cleanHtml($i18n->getDescription());
                $path = $i18n->getUrlPath();
                $model->link_rewrite[$id] = \Tools::str2url(empty($path) ? $i18n->getName() : $path);
                $model->meta_title[$id] = $i18n->getTitleTag();
                $model->meta_keywords[$id] = $i18n->getMetaKeywords();
                $model->meta_description[$id] = $i18n->getMetaDescription();
                $model->available_now[$id] = $i18n->getDeliveryStatus();

                if (is_null($limit)) {
                    $model->description_short[$id] = $i18n->getShortDescription();
                } else {
                    $model->description_short[$id] = substr($i18n->getShortDescription(), 0, $limit);
                }
            }
        }
    }

    private function cleanHtml($html)
    {
        $events = 'onmousedown|onmousemove|onmmouseup|onmouseover|onmouseout|onload|onunload|onfocus|onblur|onchange';
        $events .= '|onsubmit|ondblclick|onclick|onkeydown|onkeyup|onkeypress|onmouseenter|onmouseleave|onerror|onselect|onreset|onabort|ondragdrop|onresize|onactivate|onafterprint|onmoveend';
        $events .= '|onafterupdate|onbeforeactivate|onbeforecopy|onbeforecut|onbeforedeactivate|onbeforeeditfocus|onbeforepaste|onbeforeprint|onbeforeunload|onbeforeupdate|onmove';
        $events .= '|onbounce|oncellchange|oncontextmenu|oncontrolselect|oncopy|oncut|ondataavailable|ondatasetchanged|ondatasetcomplete|ondeactivate|ondrag|ondragend|ondragenter|onmousewheel';
        $events .= '|ondragleave|ondragover|ondragstart|ondrop|onerrorupdate|onfilterchange|onfinish|onfocusin|onfocusout|onhashchange|onhelp|oninput|onlosecapture|onmessage|onmouseup|onmovestart';
        $events .= '|onoffline|ononline|onpaste|onpropertychange|onreadystatechange|onresizeend|onresizestart|onrowenter|onrowexit|onrowsdelete|onrowsinserted|onscroll|onsearch|onselectionchange';
        $events .= '|onselectstart|onstart|onstop';

        $html = preg_replace('/<[\s]*script/ims', '', $html);
        $html = preg_replace('/('.$events.')[\s]*=/ims', '', $html);
        $html = preg_replace('/.*script\:/ims', '', $html);
        $html = preg_replace('/<[\s]*(i?frame|form|input|embed|object)/ims', '', $html);

        return $html;
    }
}
