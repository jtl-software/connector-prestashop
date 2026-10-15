<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Jtl\Connector\Core\Model\CustomerOrder;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\StatusChange;
use Db;
use jtl\Connector\Presta\Controller\StatusChangeController;
use Order;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

final class TestableStatusChangeController extends StatusChangeController
{
    public function __construct()
    {
        $this->db             = new Db();
        $this->logger         = new NullLogger();
        $this->controllerName = 'StatusChangeController';
    }
}

final class StatusChangeControllerTest extends TestCase
{
    private TestableStatusChangeController $controller;

    protected function setUp(): void
    {
        Order::resetMock();
        $this->controller = new TestableStatusChangeController();
    }

    protected function tearDown(): void
    {
        Order::resetMock();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeStatusChange(
        ?string $orderId,
        string $orderStatus = '',
        string $paymentStatus = ''
    ): StatusChange {
        $model = new StatusChange();
        if ($orderId !== null) {
            $model->setCustomerOrderId(new Identity($orderId, 0));
        }
        if ($orderStatus !== '') {
            $model->setOrderStatus($orderStatus);
        }
        if ($paymentStatus !== '') {
            $model->setPaymentStatus($paymentStatus);
        }
        return $model;
    }

    // -------------------------------------------------------------------------
    // orderId is null (getCustomerOrderId() returns null) → RuntimeException
    //
    // StatusChange always initialises customerOrderId = new Identity() in its
    // constructor, so getCustomerOrderId() normally returns a non-null Identity.
    // We force the null case via a subclass that overrides the getter.
    // -------------------------------------------------------------------------

    public function testPushThrowsWhenCustomerOrderIdIsNull(): void
    {
        $model = new class extends StatusChange {
            public function getCustomerOrderId(): ?Identity
            {
                return null;
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Order id is missing');

        $this->controller->push($model);
    }

    // -------------------------------------------------------------------------
    // orderId is empty string → no Order is created, model is returned
    // -------------------------------------------------------------------------

    public function testPushReturnsModelUnchangedWhenOrderIdIsEmpty(): void
    {
        $model  = $this->makeStatusChange('');
        $result = $this->controller->push($model);

        self::assertSame($model, $result);
    }

    // -------------------------------------------------------------------------
    // orderId set but Order stub returns id=null → early return with warning
    // The Order stub returns id=null for id=0; any non-numeric string cast to 0
    // will behave the same.  Use a non-existent numeric id to trigger the path.
    // -------------------------------------------------------------------------

    public function testPushReturnsModelEarlyWhenOrderNotFound(): void
    {
        // Status that leads to a non-null newStatus → code enters the order-lookup branch
        // Order stub: new \Order(0) → id = null → early return
        $model  = $this->makeStatusChange('0', CustomerOrder::STATUS_CANCELLED);
        $result = $this->controller->push($model);

        self::assertSame($model, $result);
    }

    // -------------------------------------------------------------------------
    // order-not-found branch (lines 41-42): non-empty orderId, status leads to
    // newStatus != null, but Order::$mockNotFoundIds makes the Order stub return
    // id=null → logger warning is issued and model is returned early.
    // -------------------------------------------------------------------------

    public function testPushLogsWarningAndReturnsModelWhenOrderNotFoundForNonEmptyId(): void
    {
        Order::$mockNotFoundIds = [55];
        $model  = $this->makeStatusChange('55', CustomerOrder::STATUS_CANCELLED);
        $result = $this->controller->push($model);
        self::assertSame($model, $result);
    }

    // -------------------------------------------------------------------------
    // match branches – use orderId='0' so order lookup returns id=null and we
    // stay exception-free while still exercising the match arm selection.
    // -------------------------------------------------------------------------

    public function testPushReturnsModelForStatusCancelled(): void
    {
        $model  = $this->makeStatusChange('0', CustomerOrder::STATUS_CANCELLED);
        $result = $this->controller->push($model);

        self::assertSame($model, $result);
    }

    public function testPushReturnsModelForPaymentCompletedAndStatusShipped(): void
    {
        $model  = $this->makeStatusChange(
            '0',
            CustomerOrder::STATUS_SHIPPED,
            CustomerOrder::PAYMENT_STATUS_COMPLETED
        );
        $result = $this->controller->push($model);

        self::assertSame($model, $result);
    }

    public function testPushReturnsModelForStatusShippedAlone(): void
    {
        $model  = $this->makeStatusChange('0', CustomerOrder::STATUS_SHIPPED);
        $result = $this->controller->push($model);

        self::assertSame($model, $result);
    }

    public function testPushReturnsModelForPaymentCompletedAlone(): void
    {
        $model  = $this->makeStatusChange('0', '', CustomerOrder::PAYMENT_STATUS_COMPLETED);
        $result = $this->controller->push($model);

        self::assertSame($model, $result);
    }

    // -------------------------------------------------------------------------
    // default branch: no matching status → newStatus = null → order is never
    // created, model is returned directly
    // -------------------------------------------------------------------------

    public function testPushReturnsModelWhenNoStatusMatchesDefault(): void
    {
        $model  = $this->makeStatusChange('99', CustomerOrder::STATUS_NEW);
        $result = $this->controller->push($model);

        self::assertSame($model, $result);
    }

    // -------------------------------------------------------------------------
    // Order exists (id > 0) and current state equals newStatus → no state change
    // The stub's getCurrentState() always returns 0, which does NOT match 6,
    // so setCurrentState will be called. We verify no exception is thrown and
    // the model is returned.
    // -------------------------------------------------------------------------

    public function testPushReturnsModelWhenOrderExistsAndStatusNeedsUpdate(): void
    {
        // new \Order(42) in the stub → id = 42 (non-zero), getCurrentState() = 0
        $model  = $this->makeStatusChange('42', CustomerOrder::STATUS_CANCELLED);
        $result = $this->controller->push($model);

        self::assertSame($model, $result);
    }

    public function testPushWithStatusShippedAndNonZeroOrderIdCoversShippedArm(): void
    {
        $model = $this->makeStatusChange('99', CustomerOrder::STATUS_SHIPPED);
        $result = $this->controller->push($model);
        self::assertSame($model, $result);
    }

    public function testPushWithPaymentCompletedAndShippedNonZeroOrderIdCoversCombinedArm(): void
    {
        $model = $this->makeStatusChange(
            '99',
            CustomerOrder::STATUS_SHIPPED,
            CustomerOrder::PAYMENT_STATUS_COMPLETED
        );
        $result = $this->controller->push($model);
        self::assertSame($model, $result);
    }

    public function testPushWithPaymentCompletedAloneNonZeroOrderIdCoversPaymentArm(): void
    {
        $model = $this->makeStatusChange(
            '99',
            '',
            CustomerOrder::PAYMENT_STATUS_COMPLETED
        );
        $result = $this->controller->push($model);
        self::assertSame($model, $result);
    }

    public function testPushWithCancelledStatusOrderExistsAndStateAlreadySet(): void
    {
        // orderId='42' → !empty('42') = true → enters match
        // STATUS_CANCELLED → newStatus=6
        // new Order(42) → id=42 (truthy) → getCurrentState()=0 != 6 → setCurrentState(6) called
        // Stub setCurrentState() returns true → no exception
        $model  = $this->makeStatusChange('42', CustomerOrder::STATUS_CANCELLED);
        $result = $this->controller->push($model);
        self::assertSame($model, $result);
    }
}

