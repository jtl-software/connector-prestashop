<?php

declare(strict_types=1);

namespace jtl\Connector\Presta\Controller;

use Jtl\Connector\Core\Controller\DeleteInterface;
use Jtl\Connector\Core\Controller\PullInterface;
use Jtl\Connector\Core\Controller\PushInterface;
use Jtl\Connector\Core\Model\AbstractModel;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\QueryFilter;
use Jtl\Connector\Core\Model\Specific;
use Jtl\Connector\Core\Model\SpecificI18n as JtlSpecificI18n;
use Jtl\Connector\Core\Model\Statistic;
use jtl\Connector\Presta\Utils\QueryBuilder;
use Feature as PrestaSpecific;
use FeatureValue as PrestaSpecificValue;
use Jtl\Connector\Core\Model\Specific as JtlSpecific;
use Jtl\Connector\Core\Model\SpecificValue as JtlSpecificValue;
use Jtl\Connector\Core\Model\SpecificValueI18n as JtlSpecificValueI18n;

class SpecificController extends AbstractController implements PushInterface, PullInterface, DeleteInterface
{
    /**
     * @param QueryFilter $queryFilter
     * @return array<int, AbstractModel>
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function pull(QueryFilter $queryFilter): array
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->setUsePrefix(false);

        $sql = $queryBuilder
            ->select('v.id_feature')
            ->from(\_DB_PREFIX_ . 'feature_value', 'v')
            ->leftJoin(self::SPECIFIC_LINKING_TABLE, 'l', 'v.id_feature = l.endpoint_id')
            ->where('l.host_id IS NULL AND v.custom = 0')
            ->groupBy('v.id_feature')
            ->limit($queryFilter->getLimit());

        /** @var array<string, array<string, string>> $prestaSpecificsIds */
        $prestaSpecificsIds = $this->db->executeS($sql->build());

        $jtlSpecifics = [];

        foreach ($prestaSpecificsIds as $prestaSpecificsId) {
            $prestaSpecific = new PrestaSpecific((int)$prestaSpecificsId['id_feature']);
            if (empty($prestaSpecific->id)) {
                $this->logger->debug(
                    \sprintf(
                        'Specific with id %s not found, skipping',
                        $prestaSpecificsId['id_feature']
                    )
                );
                continue;
            }
            $jtlSpecifics[] = $this->createJtlSpecific($prestaSpecific);
        }

        return $jtlSpecifics;
    }

    /**
     * @param PrestaSpecific $prestaSpecific
     *
     * @return JtlSpecific
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    protected function createJtlSpecific(PrestaSpecific $prestaSpecific): JtlSpecific
    {
        return (new JtlSpecific())
            ->setIsGlobal(true)
            ->setId(new Identity((string)$prestaSpecific->id))
            ->setType('string')
            ->setI18ns(...$this->createJtlSpecificI18ns($prestaSpecific))
            ->setValues(...$this->createJtlSpecificValues($this->getPrestaSpecificValues($prestaSpecific)));
    }

    /**
     * @param array<int, array<string, string>> $prestaSpecificValues
     *
     * @return array<int, JtlSpecificValue>
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    protected function createJtlSpecificValues(array $prestaSpecificValues): array
    {
        $jtlSpecificValues = [];
        foreach ($prestaSpecificValues as $prestaSpecificValue) {
            $id                  = (int) $prestaSpecificValue['id_feature_value'];
            $jtlSpecificValues[] = (new JtlSpecificValue())
                ->setId(new Identity())
                ->setI18ns(...$this->createJtlSpecificValuesI18ns($this->getPrestaSpecificValueI18ns($id)));
        }

        return $jtlSpecificValues;
    }

    /**
     * @param array<int,array<string,string>> $prestaSpecificValueI18ns
     * @return array<int, JtlSpecificValueI18n>
     *
     * @throws \PrestaShopDatabaseException
     */
    protected function createJtlSpecificValuesI18ns(array $prestaSpecificValueI18ns): array
    {
        $jtlSpecificValueI18ns = [];

        foreach ($prestaSpecificValueI18ns as $prestaSpecificValueI18n) {
            $jtlSpecificValueI18ns[] = (new JtlSpecificValueI18n())
                ->setLanguageIso($this->getJtlLanguageIsoFromLanguageId((int)$prestaSpecificValueI18n['id_lang']))
                ->setValue((string)$prestaSpecificValueI18n['value']);
        }

        return $jtlSpecificValueI18ns;
    }

    /**
     * @param PrestaSpecific $prestaSpecific
     * @return array<int, JtlSpecificI18n>
     * @throws \PrestaShopDatabaseException
     */
    protected function createJtlSpecificI18ns(PrestaSpecific $prestaSpecific): array
    {
        $jtlI18ns    = [];
        $prestaI18ns = $this->getPrestaSpecificI18ns($prestaSpecific);

        foreach ($prestaI18ns as $prestaI18n) {
            $jtlI18ns[] = (new JtlSpecificI18n())
                ->setName($prestaI18n['name'])
                ->setLanguageIso($this->getJtlLanguageIsoFromLanguageId((int)$prestaI18n['id_lang']));
        }

        return $jtlI18ns;
    }

    /**
     * @param PrestaSpecific $prestaSpecific
     * @return array<int,array<string,string>>
     * @throws \PrestaShopDatabaseException
     */
    protected function getPrestaSpecificI18ns(PrestaSpecific $prestaSpecific): array
    {
        $queryBuilder = new QueryBuilder();

        $sql = $queryBuilder
            ->select('*')
            ->from('feature_lang')
            ->where('id_feature = ' . $prestaSpecific->id);

        /** @var array<int,array<string,string>> $result */
        $result = $this->db->executeS($sql->build());
        return $result;
    }

    /**
     * @param PrestaSpecific $prestaSpecific
     * @return array<int,array<string,string>>
     * @throws \PrestaShopDatabaseException
     */
    protected function getPrestaSpecificValues(PrestaSpecific $prestaSpecific): array
    {
        $queryBuilder = new QueryBuilder();

        $sql = $queryBuilder
            ->select('*')
            ->from('feature_value')
            ->where('custom = 0 AND id_feature = ' . $prestaSpecific->id);

        /** @var array<int,array<string,string>> $result */
        $result = $this->db->executeS($sql->build());
        return $result;
    }

    /**
     * @param int $id
     *
     * @return array<int,array<string,string>>
     * @throws \PrestaShopDatabaseException|\PrestaShopException
     */
    protected function getPrestaSpecificValueI18ns(int $id): array
    {
        $queryBuilder = new QueryBuilder();

        $sql = $queryBuilder
            ->select('*')
            ->from('feature_value_lang')
            ->where('id_feature_value = ' . $id);

        /** @var array<int,array<string,string>> $result */
        $result = $this->db->executeS($sql->build());
        return $result;
    }

    /**
     * @param Specific $jtlSpecific
     * @return Specific
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function push(AbstractModel $jtlSpecific): AbstractModel
    {
        /** @var JtlSpecific $jtlSpecific */
        $endpoint = $jtlSpecific->getId()->getEndpoint();
        $isNew    = $endpoint === '';

        if (!$isNew) {
            $prestaSpecific = $this->createPrestaSpecific($jtlSpecific, new PrestaSpecific((int)$endpoint));
            if (!$prestaSpecific->update()) {
                throw new \RuntimeException('Error updating specific: ' . $jtlSpecific->getI18ns()[0]->getName());
            }

            foreach ($jtlSpecific->getValues() as $value) {
                $this->createPrestaSpecificValues(new PrestaSpecificValue(), $value, (string)$prestaSpecific->id);
            }

            return $jtlSpecific;
        }

        $prestaSpecific = $this->createPrestaSpecific($jtlSpecific, new PrestaSpecific());
        if (!$prestaSpecific->add()) {
            throw new \RuntimeException('Error uploading specific ' . $jtlSpecific->getI18ns()[0]->getName());
        }

        foreach ($jtlSpecific->getValues() as $value) {
            $this->createPrestaSpecificValues(new PrestaSpecificValue(), $value, (string)$prestaSpecific->id);
        }

        return $jtlSpecific;
    }

    /**
     * @param JtlSpecific $jtlSpecific
     * @param PrestaSpecific $prestaSpecific
     * @return PrestaSpecific
     */
    protected function createPrestaSpecific(JtlSpecific $jtlSpecific, PrestaSpecific $prestaSpecific): PrestaSpecific
    {
        $translations = $this->createPrestaSpecificI18ns(...$jtlSpecific->getI18ns());

        foreach ($translations as $key => $translation) {
            $prestaSpecific->name[$key] = $translation['name'];
        }

        return $prestaSpecific;
    }

    /**
     * @param JtlSpecificI18n ...$jtlSpecificI18ns
     * @return array<int, array{name: string}>
     * @throws \PrestaShopDatabaseException
     */
    protected function createPrestaSpecificI18ns(JtlSpecificI18n ...$jtlSpecificI18ns): array
    {
        $translations = [];

        foreach ($jtlSpecificI18ns as $jtlSpecificI18n) {
            $langId = $this->getPrestaLanguageIdFromIso($jtlSpecificI18n->getLanguageIso());

            $translations[$langId]['name'] = $jtlSpecificI18n->getName();
        }

        return $translations;
    }

    /**
     * @param PrestaSpecificValue $prestaSpecificValue
     * @param JtlSpecificValue $jtlSpecificValue
     * @param string $prestaSpecificId
     * @return PrestaSpecificValue
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    protected function createPrestaSpecificValues(
        PrestaSpecificValue $prestaSpecificValue,
        JtlSpecificValue $jtlSpecificValue,
        string $prestaSpecificId
    ): PrestaSpecificValue {
        $isNew                           = $jtlSpecificValue->getId()->getEndpoint() === '';
        $prestaSpecificValue->custom     = false;
        $prestaSpecificValue->id_feature = (int) $prestaSpecificId;

        foreach ($jtlSpecificValue->getI18ns() as $jtlSpecificValueI18n) {
            $this->createPrestaSpecificValueI18ns($jtlSpecificValueI18n, $prestaSpecificValue);
        }

        if (!$isNew) {
            if (!$prestaSpecificValue->update()) {
                throw new \RuntimeException(
                    'Error updating specific value with id: ' . $jtlSpecificValue->getId()->getEndpoint()
                );
            }

            return $prestaSpecificValue;
        }

        if (!$prestaSpecificValue->save()) {
            throw new \RuntimeException(
                'Error uploading specific value with id: ' . $jtlSpecificValue->getId()->getEndpoint()
            );
        }

        return $prestaSpecificValue;
    }

    /**
     * @param JtlSpecificValueI18n $jtlSpecificValueI18n
     * @param PrestaSpecificValue $prestaSpecificValue
     * @return PrestaSpecificValue
     * @throws \PrestaShopDatabaseException
     */
    protected function createPrestaSpecificValueI18ns(
        JtlSpecificValueI18n $jtlSpecificValueI18n,
        PrestaSpecificValue $prestaSpecificValue
    ): PrestaSpecificValue {
        $langId = $this->getPrestaLanguageIdFromIso($jtlSpecificValueI18n->getLanguageIso());

        $prestaSpecificValue->value[$langId] = $jtlSpecificValueI18n->getValue();

        return $prestaSpecificValue;
    }

    /**
     * @param Specific $model
     * @return Specific
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function delete(AbstractModel $model): AbstractModel
    {
        $specific = new PrestaSpecific((int)$model->getId()->getEndpoint());

        $specific->delete();

        return $model;
    }

    /**
     * @return Statistic
     * @throws \PrestaShopException
     */
    public function statistic(): Statistic
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->setUsePrefix(false);

        $sql = $queryBuilder
            ->select('COUNT(*)')
            ->from(\_DB_PREFIX_ . 'feature', 'v')
            ->leftJoin(self::SPECIFIC_LINKING_TABLE, 'l', 'v.id_feature = l.endpoint_id')
            ->where('l.host_id IS NULL');

        $result = $this->db->getValue($sql->build());

        return (new Statistic())
            ->setAvailable((int)$result)
            ->setControllerName($this->controllerName);
    }
}
