<?php

declare(strict_types=1);

namespace jtl\Connector\Presta\Controller;

use Jtl\Connector\Core\Controller\DeleteInterface;
use Jtl\Connector\Core\Controller\PullInterface;
use Jtl\Connector\Core\Controller\PushInterface;
use Jtl\Connector\Core\Definition\IdentityType;
use Jtl\Connector\Core\Model\AbstractModel;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\Manufacturer as JtlManufacturer;
use Jtl\Connector\Core\Model\QueryFilter;
use Jtl\Connector\Core\Model\Statistic;
use jtl\Connector\Presta\Utils\QueryBuilder;
use Manufacturer as PrestaManufacturer;
use Jtl\Connector\Core\Model\ManufacturerI18n as JtlManufacturerI18n;

class ManufacturerController extends AbstractController implements PushInterface, PullInterface, DeleteInterface
{
    /**
     * @param QueryFilter $queryFilter
     * @return array|AbstractModel[]
     * @throws \PrestaShopDatabaseException
     */
    public function pull(QueryFilter $queryFilter): array
    {
        $prestaManufacturers = $this->getNotLinkedEntities(
            $queryFilter,
            self::MANUFACTURER_LINKING_TABLE,
            'manufacturer',
            'id_manufacturer'
        );

        $jtlManufacturers = [];


        foreach ($prestaManufacturers as $prestaManufacturer) {
            $jtlManufacturers[] = $this->createJtlManufacturers(
                new PrestaManufacturer((int)$prestaManufacturer['id_manufacturer'])
            );
        }

        return $jtlManufacturers;
    }

    /**
     * @param PrestaManufacturer $prestaManufacturer
     * @return JtlManufacturer
     */
    protected function createJtlManufacturers(PrestaManufacturer $prestaManufacturer): JtlManufacturer
    {
        $jtlManufacturer = (new JtlManufacturer())
            ->setId(new Identity((string)$prestaManufacturer->id))
            ->setName($prestaManufacturer->name)
            ->setI18ns(...$this->createJtlManufacturerI18ns($prestaManufacturer));

        return $jtlManufacturer;
    }

    /**
     * @param PrestaManufacturer $manufacturer
     * @return array<int, JtlManufacturerI18n>
     * @throws \PrestaShopDatabaseException
     */
    protected function createJtlManufacturerI18ns(PrestaManufacturer $manufacturer): array
    {
        $jtlI18ns = [];

        /** @var array<int, array{id_lang: int}> $languages */
        $languages = \Language::getLanguages();

        foreach ($languages as $language) {
            $langId = $language['id_lang'];

            $jtlI18ns[] = (new JtlManufacturerI18n())
                ->setLanguageIso($this->getJtlLanguageIsoFromLanguageId($langId))
                ->setDescription((string)$manufacturer->description[$langId])
                ->setMetaDescription((string)$manufacturer->meta_description[$langId])
                ->setMetaKeywords((string)$manufacturer->meta_keywords[$langId])
                ->setTitleTag((string)$manufacturer->meta_title[$langId]);
        }
        return $jtlI18ns;
    }

    /**
     * @param JtlManufacturer $jtlManufacturer
     * @return JtlManufacturer
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function push(AbstractModel $jtlManufacturer): AbstractModel
    {
        $endpoint = $jtlManufacturer->getId()->getEndpoint();
        $isNew    = $endpoint === '';

        if (!$isNew) {
            $prestaManufacturer = $this->createPrestaManufacturer(
                $jtlManufacturer,
                new PrestaManufacturer((int)$endpoint)
            );
            if (!$prestaManufacturer->update()) {
                throw new \RuntimeException('Error updating manufacturer' . $jtlManufacturer->getName());
            }

            return $jtlManufacturer;
        }

        $prestaManufacturer = $this->createPrestaManufacturer($jtlManufacturer, new PrestaManufacturer());
        if (!$prestaManufacturer->add()) {
            throw new \RuntimeException('Error uploading manufacturer' . $jtlManufacturer->getName());
        }
        $this->mapper->save(
            IdentityType::MANUFACTURER,
            $jtlManufacturer->getId()->getEndpoint(),
            (int)$prestaManufacturer->id
        );

        return $jtlManufacturer;
    }

    /**
     * @param JtlManufacturer $jtlManufacturer
     * @param PrestaManufacturer $prestaManufacturer
     * @return PrestaManufacturer
     */
    protected function createPrestaManufacturer(
        JtlManufacturer $jtlManufacturer,
        PrestaManufacturer $prestaManufacturer
    ): PrestaManufacturer {

        $translations               = $this->createPrestaManufacturerTranslations(...$jtlManufacturer->getI18ns());
        $prestaManufacturer->active = true;

        foreach ($translations as $key => $translation) {
            $prestaManufacturer->name                   = $jtlManufacturer->getName();
            $prestaManufacturer->description[$key]      = $translation['description'];
            $prestaManufacturer->meta_title[$key]       = $translation['meta_title'];
            $prestaManufacturer->meta_keywords[$key]    = $translation['meta_keywords'];
            $prestaManufacturer->meta_description[$key] = $translation['meta_description'];
        }

        return $prestaManufacturer;
    }

    /**
     * @param JtlManufacturerI18n ...$jtlManufacturerI18ns
     * @return array<int, array<string, string>>
     * @throws \PrestaShopDatabaseException
     */
    protected function createPrestaManufacturerTranslations(JtlManufacturerI18n ...$jtlManufacturerI18ns): array
    {
        $translations = [];
        foreach ($jtlManufacturerI18ns as $jtlManufacturerI18n) {
            $languageIso = $this->getPrestaLanguageIdFromIso($jtlManufacturerI18n->getLanguageIso());

            $langId                                    = $languageIso;
            $translations[$langId]['description']      = $jtlManufacturerI18n->getDescription();
            $translations[$langId]['meta_title']       = $jtlManufacturerI18n->getTitleTag();
            $translations[$langId]['meta_keywords']    = $jtlManufacturerI18n->getMetaKeywords();
            $translations[$langId]['meta_description'] = $jtlManufacturerI18n->getMetaDescription();
        }

        return $translations;
    }

    /**
     * @param JtlManufacturer $model
     * @return JtlManufacturer
     * @throws \PrestaShopException
     */
    public function delete(AbstractModel $model): AbstractModel
    {
        $manufacturer = new \Manufacturer((int)$model->getId()->getEndpoint());

        $manufacturer->delete();

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
            ->from(\_DB_PREFIX_ . 'manufacturer', 'm')
            ->leftJoin(self::MANUFACTURER_LINKING_TABLE, 'l', 'm.id_manufacturer = l.endpoint_id')
            ->where('l.host_id IS NULL');

        $result = $this->db->getValue($sql->build());

        return (new Statistic())
            ->setAvailable((int)$result)
            ->setControllerName($this->controllerName);
    }
}
