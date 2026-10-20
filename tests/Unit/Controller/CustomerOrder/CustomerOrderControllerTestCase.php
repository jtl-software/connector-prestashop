<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\CustomerOrder;

use Address;
use Carrier;
use Cart;
use Configuration;
use Context;
use Customer;
use Db;
use Jtl\Connector\Core\Model\CustomerOrder as JtlCustomerOrder;
use Jtl\Connector\Core\Model\CustomerOrderBillingAddress as JtlCustomerOrderBillingAddress;
use Jtl\Connector\Core\Model\CustomerOrderItem as JtlCustomerOrderItem;
use Jtl\Connector\Core\Model\CustomerOrderShippingAddress as JtlCustomerOrderShippingAddress;
use jtl\Connector\Presta\Controller\CustomerOrderController;
use Order;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionMethod;
use TypeError;

/**
 * Testable subclass that:
 * - bypasses the constructor dependency on PrimaryKeyMapper / Db::getInstance()
 * - overrides DB-dependent helper methods with deterministic stubs
 * - exposes protected methods for direct testing
 */
final class TestableCustomerOrderController extends CustomerOrderController
{
    public function __construct()
    {
        $this->db             = new Db();
        $this->logger         = new NullLogger();
        $this->controllerName = 'CustomerOrderController';
    }

    protected function getJtlLanguageIsoFromLanguageId(string|int $langId): string
    {
        return 'eng';
    }

    protected function getJtlCountryIsoFromPrestaCountryId(int|string $id): string
    {
        return 'DE';
    }

    protected function determineSalutation(Customer $c): string
    {
        return match ($c->id_gender) {
            1       => 'm',
            2       => 'w',
            default => '',
        };
    }

    public function exposeCreateJtlCustomerOrderItem(array $data): JtlCustomerOrderItem
    {
        return $this->createJtlCustomerOrderItem($data);
    }

    public function exposeCreateJtlCustomerOrderBillingAddress(
        Address  $addr,
        Customer $cust
    ): JtlCustomerOrderBillingAddress {
        return $this->createJtlCustomerOrderBillingAddress($addr, $cust);
    }

    public function exposeCreateJtlCustomerOrderShippingAddress(
        Address  $addr,
        Customer $cust
    ): JtlCustomerOrderShippingAddress {
        return $this->createJtlCustomerOrderShippingAddress($addr, $cust);
    }

    public function exposeSetStates(Order $order, JtlCustomerOrder $jtlOrder): void
    {
        $method = new ReflectionMethod(CustomerOrderController::class, 'setStates');
        $method->setAccessible(true);
        $method->invoke($this, $order, $jtlOrder);
    }

    public function injectDb(Db $db): void
    {
        $this->db = $db;
    }

    public function exposeGetShippingLineItem(Order $order, Carrier $carrier): JtlCustomerOrderItem
    {
        return $this->getShippingLineItem($order, $carrier);
    }

    public function exposeGetCustomerOrderItems(Cart $cart): array
    {
        return $this->getCustomerOrderItems($cart);
    }

    public function exposeAddDiscountItems(Order $order, JtlCustomerOrder $jtlOrder): void
    {
        $method = new ReflectionMethod(CustomerOrderController::class, 'addDiscountItems');
        $method->setAccessible(true);
        $method->invoke($this, $order, $jtlOrder);
    }

    /** @var JtlCustomerOrderItem[]|null */
    private ?array $mockOrderItems = null;
    private ?JtlCustomerOrderItem $mockShippingItem = null;

    public function setMockOrderItems(array $items): void
    {
        $this->mockOrderItems = $items;
    }

    public function setMockShippingItem(JtlCustomerOrderItem $item): void
    {
        $this->mockShippingItem = $item;
    }

    protected function getCustomerOrderItems(Cart $cart): array
    {
        if ($this->mockOrderItems !== null) {
            return $this->mockOrderItems;
        }
        return parent::getCustomerOrderItems($cart);
    }

    protected function getShippingLineItem(Order $order, Carrier $carrier): JtlCustomerOrderItem
    {
        if ($this->mockShippingItem !== null) {
            return $this->mockShippingItem;
        }
        return parent::getShippingLineItem($order, $carrier);
    }

    public function exposeCreateJtlCustomerOrder(Order $order): JtlCustomerOrder
    {
        return $this->createJtlCustomerOrder($order);
    }

    /** @var JtlCustomerOrder[]|null */
    private ?JtlCustomerOrder $mockCreatedOrdersContainer = null;
    /** @var JtlCustomerOrder[]|null */
    private ?array $mockCreatedOrders = null;
    private int $mockCreatedOrdersIndex = 0;

    public function setMockCreatedOrders(array $orders): void
    {
        $this->mockCreatedOrders      = $orders;
        $this->mockCreatedOrdersIndex = 0;
    }

    protected function createJtlCustomerOrder(Order $prestaOrder): JtlCustomerOrder
    {
        if ($this->mockCreatedOrders !== null) {
            return $this->mockCreatedOrders[$this->mockCreatedOrdersIndex++];
        }
        return parent::createJtlCustomerOrder($prestaOrder);
    }
}

/**
 * Variant that forces a TypeError inside the address-creation helpers
 * so the catch(TypeError) branch can be exercised.
 */
final class TestableCustomerOrderControllerForTypeError extends CustomerOrderController
{
    public function __construct()
    {
        $this->db             = new Db();
        $this->logger         = new NullLogger();
        $this->controllerName = 'CustomerOrderController';
    }

    protected function getJtlCountryIsoFromPrestaCountryId(int|string $id): string
    {
        throw new TypeError('Simulated TypeError from country lookup');
    }

    protected function determineSalutation(Customer $c): string
    {
        return '';
    }

    public function exposeCreateJtlCustomerOrderBillingAddress(
        Address  $addr,
        Customer $cust
    ): JtlCustomerOrderBillingAddress {
        return $this->createJtlCustomerOrderBillingAddress($addr, $cust);
    }

    public function exposeCreateJtlCustomerOrderShippingAddress(
        Address  $addr,
        Customer $cust
    ): JtlCustomerOrderShippingAddress {
        return $this->createJtlCustomerOrderShippingAddress($addr, $cust);
    }
}

abstract class CustomerOrderControllerTestCase extends TestCase
{
    protected TestableCustomerOrderController $controller;

    protected function setUp(): void
    {
        Db::resetInstance();
        Configuration::resetAll();
        Customer::resetMock();
        $this->controller = new TestableCustomerOrderController();
    }

    protected function tearDown(): void
    {
        Configuration::resetAll();
        Db::resetInstance();
        Context::resetContext();
        Customer::resetMock();
    }
}
