<?php

declare(strict_types=1);

namespace jtl\Connector\Presta\Controller;

use Address as PrestaAddress;
use Carrier as PrestaCarrier;
use Cart as PrestaCart;
use Currency as PrestaCurrency;
use Customer as PrestaCustomer;
use Jtl\Connector\Core\Controller\PullInterface;
use Jtl\Connector\Core\Model\AbstractModel;
use Jtl\Connector\Core\Model\CustomerOrder as JtlCustomerOrder;
use Jtl\Connector\Core\Model\CustomerOrderBillingAddress as JtlCustomerOrderBillingAddress;
use Jtl\Connector\Core\Model\CustomerOrderItem as JtlCustomerOrderItem;
use Jtl\Connector\Core\Model\CustomerOrderShippingAddress as JtlCustomerOrderShippingAddress;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\QueryFilter;
use Jtl\Connector\Core\Model\Statistic;
use jtl\Connector\Presta\Utils\QueryBuilder;
use jtl\Connector\Presta\Utils\Utils;
use Order as PrestaCustomerOrder;
use OrderCarrier as PrestaOrderCarrier;

class CustomerOrderController extends AbstractController implements PullInterface
{
    /**
     * @param QueryFilter $queryFilter
     *
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
     *
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

        if (!\is_int($prestaCustomer->id)) {
            throw new \RuntimeException(
                \sprintf(
                    'Can\'t load Customer from Order %s, probably deleted Customer.',
                    $prestaOrder->id
                )
            );
        }

        if ($prestaCustomer->id !== $prestaOrder->id_customer) {
            throw new \RuntimeException(
                \sprintf(
                    'Customer ID %s from Order %s does not match Customer ID from Customer %s',
                    $prestaOrder->id_customer,
                    $prestaOrder->id,
                    $prestaCustomer->id
                )
            );
        }

        $orderNumber = $prestaOrder->reference;
        // check if a reference exists multiple times
        $qb    = new QueryBuilder();
        $sql   = $qb->select('COUNT(*)')
            ->from('orders')
            ->where("reference = '$orderNumber'");
        $count = $this->db->getValue($sql);
        if ($count > 1) {
            $orderNumber = \sprintf("%s-%s", $orderNumber, $prestaOrder->id);
        }


        $jtlOrder = (new JtlCustomerOrder())
            ->setId(new Identity((string)$prestaOrder->id))
            ->setCustomerId(new Identity((string)$prestaOrder->id_customer))
            ->setBillingAddress(
                $this->createJtlCustomerOrderBillingAddress(
                    $prestaInvoiceAddress,
                    $prestaCustomer
                )
            )
            ->setCreationDate($this->createDateTime($prestaOrder->date_add))
            ->setCurrencyIso($prestaCurrency->iso_code)
            ->setLanguageIso($this->getJtlLanguageIsoFromLanguageId($prestaOrder->id_lang))
            ->setOrderNumber($orderNumber)
            ->setPaymentDate($this->createDateTime($prestaOrder->invoice_date))
            ->setPaymentModuleCode(Utils::mapPaymentModuleCode($prestaOrder->module))
            ->setShippingAddress(
                $this->createJtlCustomerOrderShippingAddress(
                    $prestaDeliveryAddress,
                    $prestaCustomer
                )
            )
            ->setShippingDate($this->createDateTime($prestaOrder->delivery_date))
            ->setShippingInfo($prestaOrder->getShippingNumber())
            ->setShippingMethodName($prestaCarrier->name)
            ->setTotalSum((float)$prestaOrder->total_paid)
            ->setTotalSumGross((float)$prestaOrder->total_paid_tax_incl)
            ->setItems(...$this->getCustomerOrderItems($prestaCart));

        $jtlOrder->addItem($this->getShippingLineItem($prestaOrder, $prestaCarrier));
        if ($prestaOrder->getCartRules() > 0) {
            $this->addDiscountItems($prestaOrder, $jtlOrder);
        }

        $this->setStates($prestaOrder, $jtlOrder);

        return $jtlOrder;
    }

    /**
     * @param PrestaCustomerOrder $prestaOrder
     * @param JtlCustomerOrder    $jtlOrder
     *
     * @return void
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private function addDiscountItems(PrestaCustomerOrder $prestaOrder, JtlCustomerOrder $jtlOrder): void
    {
        $rules = $prestaOrder->getCartRules();
        /** @var array{
         *     id_order_cart_rule: int,
         *     id_order: int,
         *     id_cart_rule: int,
         *     id_order_invoice: int,
         *     name: string,
         *     value: float,
         *     value_tax_excl: float,
         *     free_shipping: int,
         *     deleted: int,
         *     } $rule
         */
        foreach ($rules as $rule) {
            $item = new JtlCustomerOrderItem();
            $code = "";
            if ($rule['id_cart_rule']) {
                $cartRule = new \CartRule($rule['id_cart_rule']);
                $code     = $cartRule->code;
            }
            $item->setType(JtlCustomerOrderItem::TYPE_COUPON)
                ->setId(new Identity(\sprintf('rule_%s', $rule['id_order_cart_rule'])))
                ->setName($rule['name'])
                ->setPrice(((float)$rule['value_tax_excl']) * -1)
                ->setPriceGross(((float)$rule['value']) * -1)
                ->setVat(\round(($rule['value'] / $rule['value_tax_excl'] - 1) * 100, 2))
                ->setQuantity(1)
                ->setNote(\sprintf('Code: %s', $code));
            $jtlOrder->addItem($item);
        }
    }

    /**
     * @param PrestaCustomerOrder $prestaOrder
     * @param PrestaCarrier       $carrier
     *
     * @return JtlCustomerOrderItem
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */

    protected function getShippingLineItem(
        PrestaCustomerOrder $prestaOrder,
        PrestaCarrier       $carrier
    ): JtlCustomerOrderItem {
        $orderCarrierId = $prestaOrder->getIdOrderCarrier();
        $orderCarrier   = new PrestaOrderCarrier($orderCarrierId);

        return (new JtlCustomerOrderItem())
            ->setName($carrier->name)
            ->setPrice((float)$orderCarrier->shipping_cost_tax_excl)
            ->setPriceGross((float)$orderCarrier->shipping_cost_tax_incl)
            ->setQuantity(1)
            ->setType(JtlCustomerOrderItem::TYPE_SHIPPING)
            ->setVat((float)$prestaOrder->carrier_tax_rate);
    }

    /**
     * @param PrestaCart $prestaCart
     *
     * @return array
     */
    protected function getCustomerOrderItems(PrestaCart $prestaCart): array
    {
        $context           = \Context::getContext();
        $context->cart     = $prestaCart;
        $context->country  = $prestaCart->getTaxCountry();
        $context->shop     = new \Shop($prestaCart->getShopId());
        $context->currency = new \Currency($prestaCart->id_currency);

        $prestaProducts = $prestaCart->getProducts(keepOrderPrices: true);
        $jtlOrderItems  = [];

        foreach ($prestaProducts as $prestaProduct) {
            $jtlOrderItems[] = $this->createJtlCustomerOrderItem($prestaProduct);
        }

        return $jtlOrderItems;
    }

    /**
     * @param array{
     *     id_product: int,
     *     id_product_attribute: int,
     *     name: string,
     *     price_with_reduction_without_tax: float,
     *     price_with_reduction: float,
     *     cart_quantity: int,
     *     reference: string,
     *     rate: float,
     *     attributes: string
     *     } $prestaProduct
     *
     * @return JtlCustomerOrderItem
     */
    protected function createJtlCustomerOrderItem(array $prestaProduct): JtlCustomerOrderItem
    {
        $id   = new Identity((string)$prestaProduct['id_product']);
        $name = $prestaProduct['name'];
        if (!empty($prestaProduct['id_product_attribute']) && $prestaProduct['id_product_attribute'] > 0) {
            // is variant
            $id   = new Identity(
                \sprintf(
                    '%s_%s',
                    $prestaProduct['id_product'],
                    $prestaProduct['id_product_attribute']
                )
            );
            $name = \sprintf('%s | %s', $prestaProduct['name'], $prestaProduct['attributes']);
        }
        return (new JtlCustomerOrderItem())
            ->setProductId($id)
            ->setName($name)
            ->setPrice($prestaProduct['price_with_reduction_without_tax'])
            ->setPriceGross($prestaProduct['price_with_reduction'])
            ->setQuantity($prestaProduct['cart_quantity'])
            ->setSku($prestaProduct['reference'])
            ->setType(JtlCustomerOrderItem::TYPE_PRODUCT)
            ->setVat($prestaProduct['rate']);
    }

    /**
     * @param PrestaAddress  $prestaAddress
     * @param PrestaCustomer $prestaCustomer
     *
     * @return JtlCustomerOrderBillingAddress
     * @throws \PrestaShopDatabaseException
     */
    protected function createJtlCustomerOrderBillingAddress(
        PrestaAddress  $prestaAddress,
        PrestaCustomer $prestaCustomer
    ): JtlCustomerOrderBillingAddress {
        try {
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
        } catch (\TypeError $e) {
            $message = \sprintf(
                'Error while creating Billing Address for Customer %s | Error: %s',
                $prestaCustomer->id,
                $e->getMessage()
            );
            $this->logger->info($message, ['customer' => $prestaCustomer, 'exception' => $e]);
            throw new \RuntimeException($message);
        }
    }

    /**
     * @param PrestaAddress  $prestaAddress
     * @param PrestaCustomer $prestaCustomer
     *
     * @return JtlCustomerOrderShippingAddress
     * @throws \PrestaShopDatabaseException
     */
    protected function createJtlCustomerOrderShippingAddress(
        PrestaAddress  $prestaAddress,
        PrestaCustomer $prestaCustomer
    ): JtlCustomerOrderShippingAddress {
        try {
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
        } catch (\TypeError $e) {
            $message = \sprintf(
                'Error while creating Shipping Address for Customer %s | Error: %s',
                $prestaCustomer->id,
                $e->getMessage()
            );
            $this->logger->info($message, ['customer' => $prestaCustomer, 'exception' => $e]);
            throw new \RuntimeException($message);
        }
    }

    /**
     * @param PrestaCustomerOrder $prestaOrder
     * @param JtlCustomerOrder    $jtlOrder
     *
     * @return void
     */
    private function setStates(PrestaCustomerOrder $prestaOrder, JtlCustomerOrder $jtlOrder): void
    {
        // CustomerOrders are always unpaid on first import, payment gets set via Payment pull
        $jtlOrder->setPaymentStatus(JtlCustomerOrder::PAYMENT_STATUS_UNPAID);
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


        $sql2   = \sprintf("SHOW COLUMNS FROM `%sorders` LIKE 'deleted';", \_DB_PREFIX_);
        $result = $this->db->executeS($sql2);
        if (\count($result) !== 0) {
            $sql->where('o.deleted = 0');
        }

        $result = $this->db->getValue($sql);

        return (new Statistic())
            ->setAvailable((int)$result)
            ->setControllerName($this->controllerName);
    }
}
