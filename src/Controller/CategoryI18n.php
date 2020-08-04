<?php

namespace jtl\Connector\Presta\Controller;

use jtl\Connector\Presta\Utils\Utils;

class CategoryI18n extends BaseController
{
    public function pullData($data, $model)
    {
        $result = $this->db->executeS(
            '
			SELECT c.*
			FROM '._DB_PREFIX_.'category_lang c
			WHERE c.id_category = '.$data['id_category']
        );

        $return = [];

        foreach ($result as $data) {
            $model = $this->mapper->toHost($data);

            $return[] = $model;
        }

        return $return;
    }

    public function pushData($data, $model)
    {
        foreach ($data->getI18ns() as $i18n) {
            $id = Utils::getInstance()->getLanguageIdByIso($i18n->getLanguageISO());

            $model->name[$id] = $i18n->getName();
            $model->description[$id] = $this->cleanHtml($i18n->getDescription());
            $path = $i18n->getUrlPath();
            $model->link_rewrite[$id] = \Tools::str2url(empty($path) ? $i18n->getName() : $path);
            $model->meta_title[$id] = $i18n->getTitleTag();
            $model->meta_keywords[$id] = $i18n->getMetaKeywords();
            $model->meta_description[$id] = $i18n->getMetaDescription();
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
