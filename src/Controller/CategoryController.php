<?php

declare(strict_types=1);

namespace jtl\Connector\Presta\Controller;

use Category as PrestaCategory;
use Jtl\Connector\Core\Controller\DeleteInterface;
use Jtl\Connector\Core\Controller\PullInterface;
use Jtl\Connector\Core\Controller\PushInterface;
use Jtl\Connector\Core\Definition\IdentityType;
use Jtl\Connector\Core\Model\Category as JtlCategory;
use Jtl\Connector\Core\Model\CategoryI18n as JtlCategoryI18n;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\QueryFilter;
use Jtl\Connector\Core\Model\AbstractModel;
use Jtl\Connector\Core\Model\Statistic;
use jtl\Connector\Presta\Utils\QueryBuilder;
use PrestaShopDatabaseException;
use PrestaShopException;

class CategoryController extends AbstractController implements PullInterface, PushInterface, DeleteInterface
{
    /**
     * @param QueryFilter $queryFilter
     * @return array|AbstractModel[]
     * @throws PrestaShopDatabaseException
     */
    public function pull(QueryFilter $queryFilter): array
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->setUsePrefix(false);

        $sql = $queryBuilder
            ->select('c.*')
            ->from(\_DB_PREFIX_ . 'category', 'c')
            ->leftJoin(self::CATEGORY_LINKING_TABLE, 'l', 'c.id_category = l.endpoint_id')
            ->where('l.host_id IS NULL AND c.id_parent != 0')
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

    /**
     * @param array $prestaCategory
     * @return JtlCategory
     * @throws PrestaShopDatabaseException
     */
    protected function createJtlCategory(array $prestaCategory): JtlCategory
    {
        $jtlCategory = (new JtlCategory())
            ->setId(new Identity((string)$prestaCategory['id_category']))
            ->setIsActive((bool)$prestaCategory['active'])
            ->setLevel($prestaCategory['level_depth'])
            ->setParentCategoryId(
                $prestaCategory['id_parent'] == PrestaCategory::getRootCategory()->id
                || $prestaCategory['id_parent'] == 2
                ? new Identity('')
                : new Identity((string)$prestaCategory['id_parent'])
            );


        $jtlCategoryI18ns = $this->createJtlCategoryTranslations($prestaCategory['id_category']);

        $jtlCategory
            ->setI18ns(...$jtlCategoryI18ns);

        return $jtlCategory;
    }

    /**
     * @param array $prestaCategoryI18n
     * @return JtlCategoryI18n
     * @throws PrestaShopDatabaseException
     */
    protected function createJtlCategoryTranslation(array $prestaCategoryI18n): JtlCategoryI18n
    {
        return (new JtlCategoryI18n())
            ->setName($prestaCategoryI18n['name'])
            ->setTitleTag($prestaCategoryI18n['meta_title'])
            ->setDescription((string)$prestaCategoryI18n['description'])
            ->setMetaDescription((string)$prestaCategoryI18n['meta_description'])
            ->setMetaKeywords($prestaCategoryI18n['meta_keywords'])
            ->setLanguageIso($this->getJtlLanguageIsoFromLanguageId($prestaCategoryI18n['id_lang']));
    }

    /**
     * @param int $prestaCategoryId
     * @return array
     * @throws PrestaShopDatabaseException
     */
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

    /**
     * @param AbstractModel $jtlCategory
     * @return AbstractModel
     * @throws PrestaShopException
     * @throws PrestaShopDatabaseException
     * @throws \Exception
     */
    public function push(AbstractModel $jtlCategory): AbstractModel
    {
        /** @var JtlCategory $jtlCategory */
        $endpoint = $jtlCategory->getId()->getEndpoint();
        $isNew    = $endpoint === '';

        if (!$isNew) {
            $prestaCategory = $this->createPrestaCategory($jtlCategory, new PrestaCategory($endpoint));
            if (!$prestaCategory->update()) {
                throw new \RuntimeException('Error updating category' . $jtlCategory->getI18ns()[0]->getName());
            }

            return $jtlCategory;
        }

        $prestaCategory = $this->createPrestaCategory($jtlCategory, new PrestaCategory());
        if (!$prestaCategory->add()) {
            throw new \RuntimeException('Error uploading category' . $jtlCategory->getI18ns()[0]->getName());
        }

        $this->mapper->save(IdentityType::CATEGORY, $prestaCategory->id, $jtlCategory->getId()->getHost());

        return $jtlCategory;
    }

    /**
     * @param JtlCategory $jtlCategory
     * @param PrestaCategory $prestaCategory
     * @return PrestaCategory
     */
    protected function createPrestaCategory(JtlCategory $jtlCategory, PrestaCategory $prestaCategory): PrestaCategory
    {
        $translations              = $this->createPrestaCategoryTranslations(...$jtlCategory->getI18ns());
        $prestaCategory->active    = $jtlCategory->getIsActive();
        $prestaCategory->position  = $jtlCategory->getSort();
        $prestaCategory->id_parent =
            empty($jtlCategory->getParentCategoryId()->getEndpoint())
                ? PrestaCategory::getRootCategory()->id
                : $jtlCategory->getParentCategoryId()->getEndpoint();

        foreach ($translations as $key => $translation) {
            $prestaCategory->name[$key]             = \preg_replace('/[^a-zA-Z0-9-_]/', '_', $translation['name']);
            $prestaCategory->description[$key]      = $translation['description'];
            $prestaCategory->meta_description[$key] = $translation['metaDescription'];
            $prestaCategory->meta_keywords[$key]    = $translation['metaKeywords'];
            $prestaCategory->link_rewrite[$key]     = \preg_replace('/[^a-zA-Z0-9-_]/', '_', $translation['url']);
        }

        return $prestaCategory;
    }

    /**
     * @param JtlCategoryI18n ...$jtlCategoryI18ns
     * @return array
     * @throws PrestaShopDatabaseException
     * @throws \RuntimeException
     */
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
            $translations[$langId]['url']             = empty($jtlCategoryI18n->getUrlPath())
                ? $jtlCategoryI18n->getName()
                : $jtlCategoryI18n->getUrlPath();
        }

        return $translations;
    }

    /**
     * @param AbstractModel $model
     * @return AbstractModel
     * @throws PrestaShopException
     */
    public function delete(AbstractModel $model): AbstractModel
    {
        $category = new PrestaCategory($model->getId()->getEndpoint());

        $category->delete();

        return $model;
    }


    /**
     * @return Statistic
     */
    public function statistic(): Statistic
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->setUsePrefix(false);

        $sql = $queryBuilder
            ->select('COUNT(*)')
            ->from(\_DB_PREFIX_ . 'category', 'c')
            ->leftJoin(self::CATEGORY_LINKING_TABLE, 'l', 'c.id_category = l.endpoint_id')
            ->where('l.host_id IS NULL AND c.id_parent != 0 AND c.is_root_category = 0');

        $result = $this->db->getValue($sql);

        return (new Statistic())
            ->setAvailable((int)$result)
            ->setControllerName($this->controllerName);
    }
}
