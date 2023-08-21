<?php

namespace jtl\Connector\Presta\Controller;

use Category as PrestaCategory;
use Jtl\Connector\Core\Controller\DeleteInterface;
use Jtl\Connector\Core\Controller\PullInterface;
use Jtl\Connector\Core\Controller\PushInterface;
use Jtl\Connector\Core\Model\Category as JtlCategory;
use Jtl\Connector\Core\Model\CategoryI18n as JtlCategoryI18n;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\QueryFilter;
use Jtl\Connector\Core\Model\AbstractModel;
use Jtl\Connector\Core\Model\Statistic;
use jtl\Connector\Presta\Utils\QueryBuilder;
use PrestaShopException;

class CategoryController extends AbstractController implements PullInterface, PushInterface, DeleteInterface
{
    private static array $idCache = [];

    public function pull(QueryFilter $queryFilter): array
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->setUsePrefix(false);

        $sql = $queryBuilder
            ->select('c.*')
            ->from(\_DB_PREFIX_ . 'category', 'c')
            ->leftJoin('jtl_connector_link_category', 'l', 'c.id_category = l.endpoint_id')
            ->where('l.host_id IS NULL AND c.id_parent != 0 AND c.is_root_category = 0')
            ->orderBy('c.nleft')
            ->limit($this->db->escape($queryFilter->getLimit()));

        $prestaCategories = $this->db->executeS($sql);

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
            ->setLanguageIso($this->getJtlLanguageIsoFromLanguageId($prestaCategory['id_lang']));
    }

    protected function createJtlCategoryTranslations(int $prestaCategoryId): array
    {
        $shopId = \Context::getContext()->shop->id;

        $sql = (new QueryBuilder())
            ->select('cl.*')
            ->from('category_lang', 'cl')
            ->leftJoin('lang', 'l', 'l.id_lang = cl.id_lang')
            ->where("cl.id_category = $prestaCategoryId AND cl.id_shop = $shopId");

        $results = $this->db->executeS($sql);

        $i18ns = [];

        foreach ($results as $result) {
            $i18ns[] = $this->createJtlCategoryTranslation($result);
        }

        return $i18ns;
    }

    public function push(AbstractModel $jtlCategory): AbstractModel
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->setUsePrefix(false);

        $prestaCategory = new PrestaCategory();
        $endpoint       = $jtlCategory->getId()->getEndpoint();
        $isNew          = $endpoint === '';

        if (!$isNew) {
            $prestaCategory = $this->createPrestaCategory($jtlCategory, new PrestaCategory($endpoint));
            $prestaCategory->update();
        }

        $prestaCategory = $this->createPrestaCategory($jtlCategory, $prestaCategory);
        $prestaCategory->add();


        return $jtlCategory;
    }

    protected function createPrestaCategory(JtlCategory $jtlCategory, PrestaCategory $prestaCategory): PrestaCategory
    {
        $translations                = $this->createPrestaCategoryTranslations(...$jtlCategory->getI18ns());
        $prestaCategory->id_parent   = $jtlCategory->getParentCategoryId()->getEndpoint();
        $prestaCategory->active      = $jtlCategory->getIsActive();
        $prestaCategory->position    = $jtlCategory->getSort();
        $prestaCategory->level_depth = $jtlCategory->getLevel();

        foreach ($translations as $key => $translation) {
            $prestaCategory->name[$key]             = $translation['name'];
            $prestaCategory->description[$key]      = $translation['description'];
            $prestaCategory->meta_description[$key] = $translation['metaDescription'];
            $prestaCategory->meta_keywords[$key]    = $translation['metaKeywords'];
            $prestaCategory->link_rewrite[$key]     = $translation['url'];
        }

        return $prestaCategory;
    }

    protected function createPrestaCategoryTranslations(JtlCategoryI18n ...$jtlCategoryI18ns): array
    {
        $translations = [];
        foreach ($jtlCategoryI18ns as $jtlCategoryI18n) {
            $languageIso = $this->getPrestaLanguageIdFromIso($jtlCategoryI18n->getLanguageIso());

            $langId                                   = $languageIso;
            $translations[$langId]['name']            = $jtlCategoryI18n->getName();
            $translations[$langId]['description']     = $jtlCategoryI18n->getDescription();
            $translations[$langId]['metaDescription'] = $jtlCategoryI18n->getMetaDescription();
            $translations[$langId]['metaKeywords']    = $jtlCategoryI18n->getMetaKeywords();
            $translations[$langId]['url']             = $jtlCategoryI18n->getUrlPath();
        }

        return $translations;
    }

    /**
     * @throws PrestaShopException
     */
    public function delete(AbstractModel $model): AbstractModel
    {
        $category = new PrestaCategory($model->getId()->getEndpoint());

        if (!$category->delete()) {
            throw new \Exception('Error deleting category with id: ' . $model->getId()->getEndpoint());
        }

        return $model;
    }

    public function statistic(): Statistic
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->setUsePrefix(false);

        $sql = $queryBuilder
            ->select('COUNT(*)')
            ->from(\_DB_PREFIX_ . 'category', 'c')
            ->leftJoin('jtl_connector_link_category', 'l', 'c.id_category = l.endpoint_id')
            ->where('l.host_id IS NULL AND c.id_parent != 0 AND c.is_root_category = 0');

        $result = $this->db->getValue($sql);

        return (new Statistic())
            ->setAvailable($result)
            ->setControllerName($this->controllerName);
    }
}
