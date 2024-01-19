<?php

declare(strict_types=1);

namespace jtl\Connector\Presta\Controller;

use Jtl\Connector\Core\Controller\PullInterface;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\QueryFilter;
use Jtl\Connector\Core\Model\Statistic;
use jtl\Connector\Presta\Utils\QueryBuilder;
use Jtl\Connector\Core\Model\Payment as JtlPayment;
use jtl\Connector\Presta\Utils\Utils;

class PaymentController extends AbstractController implements PullInterface
{
    /**
     * @param QueryFilter $queryFilter
     * @return array|\Jtl\Connector\Core\Model\AbstractModel[]
     * @throws \PrestaShopDatabaseException
     */
    public function pull(QueryFilter $queryFilter): array
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->setUsePrefix(false);

        $sql = $queryBuilder
            ->select('p.*, o.id_order, o.module, o.payment')
            ->from(\_DB_PREFIX_ . 'order_payment', 'p')
            ->leftJoin(\_DB_PREFIX_ . 'orders', 'o', 'o.reference = p.order_reference')
            ->leftJoin(self::PAYMENT_LINKING_TABLE, 'l', 'p.id_order_payment = l.endpoint_id')
            ->leftJoin(self::CUSTOMER_ORDER_LINKING_TABLE, 'co', 'co.endpoint_id = o.id_order')
            ->where('l.host_id IS NULL AND co.endpoint_id IS NOT NULL')
            ->limit($this->db->escape($queryFilter->getLimit()));

        $prestaPayments = $this->db->executeS($sql);

        $jtlPayments = [];

        foreach ($prestaPayments as $prestaPayment) {
            $jtlPayments[] = $this->createJtlPayment($prestaPayment);
        }

        return $jtlPayments;
    }

    /**
     * @param array $prestaPayment
     * @return JtlPayment
     * @throws \Exception
     */
    protected function createJtlPayment(array $prestaPayment): JtlPayment
    {
        return (new JtlPayment())
            ->setId(new Identity((string)$prestaPayment['id_order_payment']))
            ->setCustomerOrderId(new Identity((string)$prestaPayment['id_order']))
            ->setCreationDate(new \DateTime($prestaPayment['date_add']))
            ->setPaymentModuleCode(Utils::mapPaymentModuleCode($prestaPayment['payment_method']))
            ->setTotalSum(Utils::stringToFloat($prestaPayment['amount']))
            ->setTransactionId(!empty($prestaPayment['transaction_id'])
                ? $prestaPayment['transaction_id']
                : $prestaPayment['order_reference']);
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
            ->from(\_DB_PREFIX_ . 'order_payment', 'p')
            ->leftJoin(self::PAYMENT_LINKING_TABLE, 'l', 'p.id_order_payment = l.endpoint_id')
            ->where('l.host_id IS NULL AND p.transaction_id != ""');

        $result = $this->db->getValue($sql);

        return (new Statistic())
            ->setAvailable((int)$result)
            ->setControllerName($this->controllerName);
    }
}
