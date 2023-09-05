<?php

declare(strict_types=1);

namespace jtl\Connector\Presta\Controller;

use Jtl\Connector\Core\Controller\PullInterface;
use Jtl\Connector\Core\Definition\PaymentType;
use Jtl\Connector\Core\Model\AbstractModel;
use Jtl\Connector\Core\Model\CustomerOrderBillingAddress as JtlCustomerOrderBillingAddress;
use Jtl\Connector\Core\Model\CustomerOrderShippingAddress as JtlCustomerOrderShippingAddress;
use Jtl\Connector\Core\Model\CustomerOrder as JtlCustomerOrder;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\QueryFilter;
use Jtl\Connector\Core\Model\CustomerOrderItem as JtlCustomerOrderItem;
use Cart as PrestaCart;
use Jtl\Connector\Core\Model\Statistic;
use jtl\Connector\Presta\Utils\QueryBuilder;
use Order as PrestaCustomerOrder;
use Currency as PrestaCurrency;
use Address as PrestaAddress;
use Carrier as PrestaCarrier;
use Customer as PrestaCustomer;

class CustomerOrderController extends AbstractController implements PullInterface
{
    /**
     * @param QueryFilter $queryFilter
     * @return array|AbstractModel[]
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function pull(QueryFilter $queryFilter): array
    {
        $fromDate = !empty(\Configuration::get('jtlconnector_from_date'))
            ? \Configuration::get('jtlconnector_from_date')
            : null;

        $prestaOrderIds = $this->getNotLinkedEntities(
            $queryFilter,
            self::CUSTOMER_ORDER_LINKING_TABLE,
            'orders',
            'id_order',
            $fromDate
        );

        $jtlOrders = [];

        foreach ($prestaOrderIds as $prestaOrderId) {
            $jtlOrders[] = $this->createJtlCustomerOrder(new PrestaCustomerOrder($prestaOrderId['id_order']));
        }

        return $jtlOrders;
    }

    /**
     * @param PrestaCustomerOrder $prestaOrder
     * @return JtlCustomerOrder
     * @throws \PrestaShopDatabaseException
     */
    protected function createJtlCustomerOrder(PrestaCustomerOrder $prestaOrder): JtlCustomerOrder
    {
        $prestaCurrency        = new PrestaCurrency($prestaOrder->id_currency);
        $prestaCarrier         = new PrestaCarrier($prestaOrder->id_carrier);
        $prestaCustomer        = new PrestaCustomer($prestaOrder->id_customer);
        $prestaInvoiceAddress  = new PrestaAddress($prestaOrder->id_address_invoice);
        $prestaDeliveryAddress = new PrestaAddress($prestaOrder->id_address_delivery);
        $prestaCart            = new PrestaCart($prestaOrder->id_cart);

        if (\is_null($prestaOrder->id)) {
            throw new \RuntimeException("Presta Order id can't be null");
        }

        $jtlOrder = (new JtlCustomerOrder())

            ->setId(new Identity((string)$prestaOrder->id))
            ->setCustomerId(new Identity((string)$prestaOrder->id_customer))
            ->setBillingAddress($this->createJtlCustomerOrderBillingAddress(
                $prestaInvoiceAddress,
                $prestaCustomer
            ))
            ->setCreationDate(new \DateTime($prestaOrder->date_add))
            ->setCurrencyIso($prestaCurrency->iso_code)
            ->setLanguageIso($this->getJtlLanguageIsoFromLanguageId($prestaOrder->id_lang))
            ->setOrderNumber((string)$prestaOrder->id)
            ->setPaymentDate(new \DateTime($prestaOrder->invoice_date))
            ->setPaymentModuleCode($this->mapPaymentModule($prestaOrder->module))
            ->setShippingAddress($this->createJtlCustomerOrderShippingAddress(
                $prestaDeliveryAddress,
                $prestaCustomer
            ))
            ->setShippingDate(new \DateTime($prestaOrder->delivery_date))
            ->setShippingInfo($prestaOrder->getShippingNumber())
            ->setShippingMethodName($prestaCarrier->name)
            ->setTotalSum((float)$prestaOrder->total_paid)
            ->setTotalSumGross((float)$prestaOrder->total_paid_tax_incl)
            ->setItems(...$this->getCustomerOrderItems($prestaCart));

        $this->setStates($prestaOrder, $jtlOrder);

        return $jtlOrder;
    }

    /**
     * @param PrestaCart $prestaCart
     * @return array
     */
    protected function getCustomerOrderItems(PrestaCart $prestaCart): array
    {
        $prestaProducts = $prestaCart->getProducts();
        $jtlOrderItems  = [];

        foreach ($prestaProducts as $prestaProduct) {
            $jtlOrderItems[] = $this->createJtlCustomerOrderItem($prestaProduct);
        }

        return $jtlOrderItems;
    }

    /**
     * @param array{
     *     id_product:int,
     *     name: string,
     *     price_with_reduction_without_tax: float,
     *     price_with_reduction: float,
     *     cart_quantity: int,
     *     reference: string,
     *     rate: float
     *     } $prestaProduct
     * @return JtlCustomerOrderItem
     */
    protected function createJtlCustomerOrderItem(array $prestaProduct): JtlCustomerOrderItem
    {
        return (new JtlCustomerOrderItem())
            ->setProductId(new Identity((string)$prestaProduct['id_product']))
            ->setName($prestaProduct['name'])
            ->setPrice($prestaProduct['price_with_reduction_without_tax'])
            ->setPriceGross($prestaProduct['price_with_reduction'])
            ->setQuantity($prestaProduct['cart_quantity'])
            ->setSku($prestaProduct['reference'])
            ->setType(JtlCustomerOrderItem::TYPE_PRODUCT)
            ->setVat($prestaProduct['rate']);
    }

    /**
     * @param string $module
     * @return string
     */
    protected function mapPaymentModule(string $module): string
    {
        return match ($module) {
            'ps_wirepayment' => PaymentType::BANK_TRANSFER,
            'ps_cashonedlivery' => PaymentType::CASH_ON_DELIVERY,
            'paypal' => PaymentType::PAYPAL,
            'klarnapaymentsofficial' => PaymentType::KLARNA,
            default => '',
        };
    }

    /**
     * @param PrestaAddress $prestaAddress
     * @param PrestaCustomer $prestaCustomer
     * @return JtlCustomerOrderBillingAddress
     * @throws \PrestaShopDatabaseException
     */
    protected function createJtlCustomerOrderBillingAddress(
        PrestaAddress $prestaAddress,
        PrestaCustomer $prestaCustomer
    ): JtlCustomerOrderBillingAddress {
        return (new JtlCustomerOrderBillingAddress())
            ->setCity($prestaAddress->city)
            ->setCompany($prestaAddress->company)
            ->setCountryIso($this->getJtlCountryIsoFromPrestaCountryId($prestaAddress->id_country))
            ->setEMail($prestaCustomer->email)
            ->setExtraAddressLine($prestaAddress->address2)
            ->setFirstName($prestaAddress->firstname)
            ->setLastName($prestaAddress->lastname)
            ->setMobile($prestaAddress->phone_mobile)
            ->setPhone($prestaAddress->phone)
            ->setSalutation($this->determineSalutation($prestaCustomer))
            ->setVatNumber($prestaAddress->vat_number)
            ->setStreet($prestaAddress->address1)
            ->setZipCode($prestaAddress->postcode);
    }

    /**
     * @param PrestaAddress $prestaAddress
     * @param PrestaCustomer $prestaCustomer
     * @return JtlCustomerOrderShippingAddress
     * @throws \PrestaShopDatabaseException
     */
    protected function createJtlCustomerOrderShippingAddress(
        PrestaAddress $prestaAddress,
        PrestaCustomer $prestaCustomer
    ): JtlCustomerOrderShippingAddress {
        return (new JtlCustomerOrderShippingAddress())
            ->setCity($prestaAddress->city)
            ->setCompany($prestaAddress->company)
            ->setCountryIso($this->getJtlCountryIsoFromPrestaCountryId($prestaAddress->id_country))
            ->setEMail($prestaCustomer->email)
            ->setExtraAddressLine($prestaAddress->address2)
            ->setFirstName($prestaAddress->firstname)
            ->setLastName($prestaAddress->lastname)
            ->setMobile($prestaAddress->phone_mobile)
            ->setPhone($prestaAddress->phone)
            ->setSalutation($this->determineSalutation($prestaCustomer))
            ->setStreet($prestaAddress->address1)
            ->setZipCode($prestaAddress->postcode);
    }

    /**
     * @param PrestaCustomerOrder $prestaOrder
     * @param JtlCustomerOrder $jtlOrder
     * @return void
     */
    private function setStates(PrestaCustomerOrder $prestaOrder, JtlCustomerOrder $jtlOrder): void
    {
        $jtlOrder->setPaymentStatus(
            $prestaOrder->hasBeenPaid(
            ) == 1 ? JtlCustomerOrder::PAYMENT_STATUS_COMPLETED : JtlCustomerOrder::PAYMENT_STATUS_UNPAID
        );
        if ($prestaOrder->hasBeenDelivered() == 1 || $prestaOrder->hasBeenShipped() == 1) {
            $jtlOrder->setStatus(JtlCustomerOrder::STATUS_SHIPPED);
        }
    }

    /**
     * @return Statistic
     */
    public function statistic(): Statistic
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->setUsePrefix(false);
        $fromDate = !empty(\Configuration::get('jtlconnector_from_date'))
            ? 'AND o.date_add >= ' . \str_replace(
                '-',
                '',
                \Configuration::get('jtlconnector_from_date')
            )
            : '';

        $sql = $queryBuilder
            ->select('COUNT(*)')
            ->from(\_DB_PREFIX_ . 'orders', 'o')
            ->leftJoin(self::CUSTOMER_ORDER_LINKING_TABLE, 'l', 'o.id_order = l.endpoint_id')
            ->where('l.host_id IS NULL ' . $fromDate);

        $result = $this->db->getValue($sql);

        return (new Statistic())
            ->setAvailable((int)$result)
            ->setControllerName($this->controllerName);
    }
}
