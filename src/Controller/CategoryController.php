<?php

namespace jtl\Connector\Presta\Controller;

use Jtl\Connector\Core\Controller\DeleteInterface;
use Jtl\Connector\Core\Controller\PullInterface;
use Jtl\Connector\Core\Controller\PushInterface;
use Jtl\Connector\Core\Model\Category as JtlCategory;
use Jtl\Connector\Core\Model\CategoryI18n as JtlCategoryI18n;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\QueryFilter;
use Jtl\Connector\Core\Model\AbstractModel;

class CategoryController extends AbstractController implements PullInterface, PushInterface, DeleteInterface
{
    private static array $idCache = [];

    public function pull(QueryFilter $queryFilter): array
    {

        $prestaCategories = $this->db->executeS(
            '
			SELECT c.* 
			FROM ' . \_DB_PREFIX_ . 'category c
			LEFT JOIN jtl_connector_link_category l ON c.id_category = l.endpoint_id
            WHERE l.host_id IS NULL AND c.id_parent != 0 AND c.is_root_category = 0
            ORDER BY c.nleft
            LIMIT ' . $queryFilter->getLimit()
        );

        $jtlCategories = [];

        foreach ($prestaCategories as $prestaCategory) {
            $jtlCategory     = $this->createJtlCategory($prestaCategory);
            $jtlCategories[] = $jtlCategory;
        }

        return $jtlCategories;
    }

    protected function createJtlCategory($prestaCategory): JtlCategory
    {
        $jtlCategory = (new JtlCategory())
            ->setId(new Identity($prestaCategory['id_category']))
            ->setIsActive($prestaCategory['active'])
            ->setLevel($prestaCategory['level_depth']);

        if (!\is_null($prestaCategory['id_parent'])) {
            $jtlCategory
                ->setParentCategoryId(new Identity($prestaCategory['id_parent']));
        }

        $prestaCategoryI18ns = $this->createJtlCategoryTranslations($prestaCategory['id_category']);

        $jtlCategory
            ->setI18ns(...$prestaCategoryI18ns);

        return $jtlCategory;
    }

    protected function createJtlCategoryTranslation(array $prestaCategory): JtlCategoryI18n
    {
        return (new JtlCategoryI18n())
            ->setName($prestaCategory['name'])
            ->setTitleTag($prestaCategory['meta_title'])
            ->setDescription($prestaCategory['description'])
            ->setMetaDescription($prestaCategory['meta_description'])
            ->setMetaKeywords($prestaCategory['meta_keywords'])
            ->setLanguageIso($this->getPrestaLanguage($prestaCategory['id_lang']));
    }

    protected function createJtlCategoryTranslations(int $prestaCategoryId): array
    {
        $results = $this->db->executeS(
            \sprintf(
                'SELECT cl.*
			FROM ' . \_DB_PREFIX_ . 'category_lang cl
			LEFT JOIN ' . \_DB_PREFIX_ . 'lang AS l ON l.id_lang = cl.id_lang
			WHERE cl.id_category = %s AND cl.id_shop = %s',
                $prestaCategoryId,
                \Context::getContext()->shop->id
            )
        );

        $i18ns = [];

        foreach ($results as $result) {
            $i18ns[] = $this->createJtlCategoryTranslation($result);
        }

        return $i18ns;
    }

    public function push(AbstractModel $model): AbstractModel
    {
        if (isset(static::$idCache[$data->getParentCategoryId()->getHost()])) {
            $data->getParentCategoryId()->setEndpoint(static::$idCache[$data->getParentCategoryId()->getHost()]);
        }

        $category = $this->mapper->toEndpoint($data);
        $category->save();

        $id = $category->id;

        $data->getId()->setEndpoint($id);

        static::$idCache[$data->getId()->getHost()] = $id;

        return $data;
    }

    public function delete(AbstractModel $model): AbstractModel
    {
        $category = new \Category($data->getId()->getEndpoint());

        if (!$category->delete()) {
            //throw new \Exception('Error deleting category with id: '.$data->getId()->getEndpoint());
        }

        return $data;
    }

    public function getStats()
    {
        return $this->db->getValue(
            '
			SELECT COUNT(*) 
			FROM ' . \_DB_PREFIX_ . 'category c
			LEFT JOIN jtl_connector_link_category l ON c.id_category = l.endpoint_id
            WHERE l.host_id IS NULL AND c.id_parent != 0 AND c.is_root_category = 0
        '
        );
    }
}
