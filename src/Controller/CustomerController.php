<?php

declare(strict_types=1);

namespace jtl\Connector\Presta\Controller;

use Jtl\Connector\Core\Controller\DeleteInterface;
use Jtl\Connector\Core\Controller\PullInterface;
use Jtl\Connector\Core\Controller\PushInterface;
use Jtl\Connector\Core\Model\AbstractModel;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\QueryFilter;
use Jtl\Connector\Core\Model\Statistic;
use jtl\Connector\Presta\Utils\QueryBuilder;
use Jtl\Connector\Core\Model\Customer as JtlCustomer;
use Customer as PrestaCustomer;
use Address as PrestaAddress;
use PrestaShop\PrestaShop\Core\Foundation\IoC\Exception;

class CustomerController extends AbstractController implements PullInterface, PushInterface, DeleteInterface
{
    // TODO: Kundengruppen importieren.

    /**
     * @param QueryFilter $queryFilter
     * @return array|AbstractModel[]
     * @throws \PrestaShopDatabaseException
     */
    public function pull(QueryFilter $queryFilter): array
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->setUsePrefix(false);

        $sql = $queryBuilder
            ->select('c.*, c.id_customer AS cid, a.*, co.iso_code, cg.id_group')
            ->from(\_DB_PREFIX_ . 'customer', 'c')
            ->leftJoin(\_DB_PREFIX_ . 'address', 'a', 'a.id_customer = c.id_customer')
            ->leftJoin(\_DB_PREFIX_ . 'country', 'co', 'co.id_country = a.id_country')
            ->leftJoin(\_DB_PREFIX_ . 'customer_group', 'cg', 'c.id_customer = cg.id_customer')
            ->leftJoin(self::CUSTOMER_LINKING_TABLE, 'l', 'c.id_customer = l.endpoint_id')
            ->where('l.host_id IS NULL AND a.id_address IS NOT NULL')
            ->groupBy('c.id_customer')
            ->limit($this->db->escape($queryFilter->getLimit()));

        $results = $this->db->executeS($sql);

        $jtlCustomers = [];

        foreach ($results as $result) {
            $jtlCustomers[] = $this->createJtlCustomer($result);
        }

        return $jtlCustomers;
    }

    /**
     * @param array $prestaCustomer
     * @return JtlCustomer
     * @throws \PrestaShopDatabaseException
     */
    protected function createJtlCustomer(array $prestaCustomer): JtlCustomer
    {
        $jtlCustomer = (new JtlCustomer())
            ->setId(new Identity((string)$prestaCustomer['id_customer']))
            ->setCustomerGroupId(new Identity((string)$prestaCustomer['id_group']))
            ->setBirthday($prestaCustomer['birthday'] === '0000-00-00'
                ? null
                : new \DateTime($prestaCustomer['birthday']))
            ->setCity($prestaCustomer['city'])
            ->setCompany($prestaCustomer['company'])
            ->setCountryIso($prestaCustomer['iso_code'])
            ->setCreationDate(new \DateTime($prestaCustomer['date_add']))
            ->setCustomerNumber((string)$prestaCustomer['id_customer'])
            ->setEMail($prestaCustomer['email'])
            ->setExtraAddressLine($prestaCustomer['address2'])
            ->setFirstName($prestaCustomer['firstname'])
            ->setHasCustomerAccount(true)
            ->setHasNewsletterSubscription((bool)$prestaCustomer['newsletter'])
            ->setIsActive((bool)$prestaCustomer['active'])
            ->setLanguageIso($this->getJtlLanguageIsoFromLanguageId($prestaCustomer['id_lang']))
            ->setLastName($prestaCustomer['lastname'])
            ->setMobile($prestaCustomer['phone_mobile'])
            ->setPhone($prestaCustomer['phone'])
            ->setSalutation($this->determineSalutation(new PrestaCustomer($prestaCustomer['id_gender'])))
            ->setStreet($prestaCustomer['address1'])
            ->setVatNumber($prestaCustomer['vat_number'])
            ->setWebsiteUrl($prestaCustomer['website'])
            ->setZipCode($prestaCustomer['postcode']);

        return $jtlCustomer;
    }

    /**
     * @param AbstractModel $jtlCustomer
     * @return AbstractModel
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException|Exception
     * @throws \Exception
     */
    public function push(AbstractModel $jtlCustomer): AbstractModel
    {
        /** @var JtlCustomer $jtlCustomer */
        $endpoint = $jtlCustomer->getId()->getEndpoint();
        $isNew    = $endpoint === '';

        if (!$isNew) {
            $prestaCustomer = $this->createPrestaCustomer($jtlCustomer, new PrestaCustomer($endpoint));
            if (!$prestaCustomer->update()) {
                throw new \Exception('Error updating Customer' . $jtlCustomer->getCustomerNumber());
            }
            $this->changeCustomerGroup($jtlCustomer, $prestaCustomer, empty($jtlCustomer->getId()->getEndpoint()));
            $prestaAddress = $this->createPrestaAddress($jtlCustomer, new PrestaAddress($endpoint), $prestaCustomer);
            if (!$prestaCustomer->update()) {
                throw new \Exception('Error updating address on Customer' . $jtlCustomer->getCustomerNumber());
            }

            return $jtlCustomer;
        }

        $prestaCustomer = $this->createPrestaCustomer($jtlCustomer, new PrestaCustomer());
        $prestaCustomer->add();
        $this->changeCustomerGroup($jtlCustomer, $prestaCustomer, $jtlCustomer->getId()->getEndpoint() === '');
        $prestaAddress = $this->createPrestaAddress($jtlCustomer, new PrestaAddress(), $prestaCustomer);
        $prestaAddress->add();

        return $jtlCustomer;
    }

    /**
     * @param JtlCustomer $jtlCustomer
     * @param PrestaCustomer $prestaCustomer
     * @return PrestaCustomer
     * @throws \PrestaShopDatabaseException
     * @throws Exception
     */
    protected function createPrestaCustomer(JtlCustomer $jtlCustomer, PrestaCustomer $prestaCustomer): PrestaCustomer
    {
        $genPassword = \Tools::passwdGen(24);
        $password    = empty($prestaCustomer->passwd) ? \Tools::hash($genPassword) : $prestaCustomer->passwd;

        $prestaCustomer->id_shop    = \Context::getContext()->shop->id;
        $prestaCustomer->birthday   = $jtlCustomer->getBirthday()->format('Y-m-d');
        $prestaCustomer->company    = $jtlCustomer->getCompany();
        $prestaCustomer->email      = $jtlCustomer->getEMail();
        $prestaCustomer->firstname  = $jtlCustomer->getFirstName();
        $prestaCustomer->newsletter = $jtlCustomer->getHasNewsletterSubscription();
        $prestaCustomer->active     = $jtlCustomer->getIsActive();
        $prestaCustomer->id_lang    = $this->getPrestaLanguageIdFromIso($jtlCustomer->getLanguageIso());
        $prestaCustomer->lastname   = $jtlCustomer->getLastName();
        $prestaCustomer->id_gender  = $jtlCustomer->getSalutation() === 'm' ? 1 : 0;
        $prestaCustomer->website    = $jtlCustomer->getWebsiteUrl();
        $prestaCustomer->passwd     = $password;

        return $prestaCustomer;
    }

    /**
     * @param JtlCustomer $jtlCustomer
     * @param PrestaCustomer $prestaCustomer
     * @param bool $isNew
     * @return void
     */
    protected function changeCustomerGroup(JtlCustomer $jtlCustomer, PrestaCustomer $prestaCustomer, bool $isNew): void
    {
        $isNew
            ? $prestaCustomer->addGroups([$jtlCustomer->getCustomerGroupId()->getEndpoint()])
            : $prestaCustomer->updateGroup([$jtlCustomer->getCustomerGroupId()->getEndpoint()]);
    }

    /**
     * @param JtlCustomer $jtlCustomer
     * @param PrestaAddress $prestaAddress
     * @param PrestaCustomer $prestaCustomer
     * @return PrestaAddress
     */
    protected function createPrestaAddress(
        JtlCustomer $jtlCustomer,
        PrestaAddress $prestaAddress,
        PrestaCustomer $prestaCustomer
    ): PrestaAddress {

        $prestaAddress->id_customer  = $prestaCustomer->id;
        $prestaAddress->alias        = $jtlCustomer->getStreet();
        $prestaAddress->firstname    = $jtlCustomer->getFirstName();
        $prestaAddress->lastname     = $jtlCustomer->getLastName();
        $prestaAddress->address1     = $jtlCustomer->getStreet();
        $prestaAddress->address2     = $jtlCustomer->getExtraAddressLine();
        $prestaAddress->postcode     = $jtlCustomer->getZipCode();
        $prestaAddress->city         = $jtlCustomer->getCity();
        $prestaAddress->phone        = $jtlCustomer->getPhone();
        $prestaAddress->phone_mobile = $jtlCustomer->getMobile();
        $prestaAddress->vat_number   = $jtlCustomer->getVatNumber();
        $prestaAddress->id_country   = $this->getPrestaCountryIdFromIso($jtlCustomer->getCountryIso());

        return $prestaAddress;
    }

    /**
     * @param AbstractModel $model
     * @return AbstractModel
     * @throws \PrestaShopException
     */
    public function delete(AbstractModel $model): AbstractModel
    {
        $customer = new PrestaCustomer($model->getId()->getEndpoint());

        if (!$customer->delete()) {
            throw new \Exception('Error deleting category with id: ' . $model->getId()->getEndpoint());
        }
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
            ->select('COUNT(DISTINCT(c.id_customer))')
            ->from(\_DB_PREFIX_ . 'customer', 'c')
            ->leftJoin(self::CUSTOMER_LINKING_TABLE, 'l', 'c.id_customer = l.endpoint_id')
            ->leftJoin(\_DB_PREFIX_ . 'address', 'a', 'c.id_customer = a.id_customer')
            ->where('l.host_id IS NULL AND a.id_address IS NOT NULL');

        $result = $this->db->getValue($sql);

        return (new Statistic())
            ->setAvailable((int)$result)
            ->setControllerName($this->controllerName);
    }
}
