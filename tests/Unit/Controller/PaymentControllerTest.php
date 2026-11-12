<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use DateTimeInterface;
use Db;
use Jtl\Connector\Core\Definition\PaymentType;
use Jtl\Connector\Core\Model\Payment as JtlPayment;
use Jtl\Connector\Core\Model\QueryFilter;
use jtl\Connector\Presta\Controller\PaymentController;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Testable subclass that:
 * - overrides the constructor to avoid \Db::getInstance()
 * - exposes the protected createJtlPayment method
 */
final class TestablePaymentController extends PaymentController
{
    public function __construct()
    {
        $this->db             = new Db();
        $this->logger         = new NullLogger();
        $this->controllerName = 'PaymentController';
    }

    public function exposeCreateJtlPayment(array $data): JtlPayment
    {
        return $this->createJtlPayment($data);
    }

    public function injectDb(Db $db): void
    {
        $this->db = $db;
    }
}

final class PaymentControllerTest extends TestCase
{
    private TestablePaymentController $controller;

    protected function setUp(): void
    {
        $this->controller = new TestablePaymentController();
    }

    // -------------------------------------------------------------------------
    // createJtlPayment
    // -------------------------------------------------------------------------

    private function basePaymentData(): array
    {
        return [
            'id_order_payment' => '100',
            'id_order'         => '200',
            'date_add'         => '2023-06-15 10:00:00',
            'payment_method'   => 'paypal',
            'amount'           => '10.50',
            'transaction_id'   => 'txn-abc123',
            'order_reference'  => 'REF-001',
        ];
    }

    public function testCreateJtlPaymentSetsIdFromIdOrderPayment(): void
    {
        $result = $this->controller->exposeCreateJtlPayment($this->basePaymentData());

        self::assertSame('100', $result->getId()->getEndpoint());
    }

    public function testCreateJtlPaymentSetsCustomerOrderIdFromIdOrder(): void
    {
        $result = $this->controller->exposeCreateJtlPayment($this->basePaymentData());

        self::assertSame('200', $result->getCustomerOrderId()->getEndpoint());
    }

    public function testCreateJtlPaymentSetsCreationDateFromDateAdd(): void
    {
        $result = $this->controller->exposeCreateJtlPayment($this->basePaymentData());

        self::assertInstanceOf(DateTimeInterface::class, $result->getCreationDate());
        self::assertSame('2023-06-15 10:00:00', $result->getCreationDate()->format('Y-m-d H:i:s'));
    }

    public function testCreateJtlPaymentUsesTransactionIdWhenNotEmpty(): void
    {
        $data = $this->basePaymentData();
        $data['transaction_id'] = 'txn-abc123';

        $result = $this->controller->exposeCreateJtlPayment($data);

        self::assertSame('txn-abc123', $result->getTransactionId());
    }

    public function testCreateJtlPaymentFallsBackToOrderReferenceWhenTransactionIdIsEmpty(): void
    {
        $data                   = $this->basePaymentData();
        $data['transaction_id'] = '';

        $result = $this->controller->exposeCreateJtlPayment($data);

        self::assertSame('REF-001', $result->getTransactionId());
    }

    public function testCreateJtlPaymentMapsPaypalModuleCodeCorrectly(): void
    {
        $data                   = $this->basePaymentData();
        $data['payment_method'] = 'paypal';

        $result = $this->controller->exposeCreateJtlPayment($data);

        self::assertSame(PaymentType::PAYPAL, $result->getPaymentModuleCode());
    }

    public function testCreateJtlPaymentMapsMollieModuleCodeCorrectly(): void
    {
        $data                   = $this->basePaymentData();
        $data['payment_method'] = 'mollie_banktransfer';

        $result = $this->controller->exposeCreateJtlPayment($data);

        self::assertSame(PaymentType::MOLLIE, $result->getPaymentModuleCode());
    }

    public function testCreateJtlPaymentMapsBankTransferModuleCodeCorrectly(): void
    {
        $data                   = $this->basePaymentData();
        $data['payment_method'] = 'ps_wirepayment';

        $result = $this->controller->exposeCreateJtlPayment($data);

        self::assertSame(PaymentType::BANK_TRANSFER, $result->getPaymentModuleCode());
    }

    public function testCreateJtlPaymentPassesThroughUnknownModuleCodeAsIs(): void
    {
        $data                   = $this->basePaymentData();
        $data['payment_method'] = 'unknown_module';

        $result = $this->controller->exposeCreateJtlPayment($data);

        self::assertSame('unknown_module', $result->getPaymentModuleCode());
    }

    public function testCreateJtlPaymentConvertsDecimalPointAmountToFloat(): void
    {
        $data           = $this->basePaymentData();
        $data['amount'] = '10.50';

        $result = $this->controller->exposeCreateJtlPayment($data);

        self::assertSame(10.5, $result->getTotalSum());
    }

    public function testCreateJtlPaymentConvertsCommaDecimalAmountToFloat(): void
    {
        $data           = $this->basePaymentData();
        $data['amount'] = '10,50';

        $result = $this->controller->exposeCreateJtlPayment($data);

        self::assertSame(10.5, $result->getTotalSum());
    }

    public function testCreateJtlPaymentConvertsZeroAmountToFloat(): void
    {
        $data           = $this->basePaymentData();
        $data['amount'] = '0';

        $result = $this->controller->exposeCreateJtlPayment($data);

        self::assertSame(0.0, $result->getTotalSum());
    }

    public function testCreateJtlPaymentConvertsLargeAmountCorrectly(): void
    {
        $data           = $this->basePaymentData();
        $data['amount'] = '9999.99';

        $result = $this->controller->exposeCreateJtlPayment($data);

        self::assertSame(9999.99, $result->getTotalSum());
    }

    public function testCreateJtlPaymentReturnsjPaymentInstance(): void
    {
        $result = $this->controller->exposeCreateJtlPayment($this->basePaymentData());

        self::assertInstanceOf(JtlPayment::class, $result);
    }

    public function testCreateJtlPaymentWithMollieExactNameReturnsMollieCode(): void
    {
        $data                   = $this->basePaymentData();
        $data['payment_method'] = 'mollie';

        $result = $this->controller->exposeCreateJtlPayment($data);

        self::assertSame(PaymentType::MOLLIE, $result->getPaymentModuleCode());
    }

    public function testCreateJtlPaymentWithKlarnaModuleCodeMapsToKlarna(): void
    {
        $data                   = $this->basePaymentData();
        $data['payment_method'] = 'klarnapaymentsofficial';

        $result = $this->controller->exposeCreateJtlPayment($data);

        self::assertSame(PaymentType::KLARNA, $result->getPaymentModuleCode());
    }

    public function testCreateJtlPaymentWithDifferentOrderIds(): void
    {
        $data              = $this->basePaymentData();
        $data['id_order_payment'] = '999';
        $data['id_order']         = '888';

        $result = $this->controller->exposeCreateJtlPayment($data);

        self::assertSame('999', $result->getId()->getEndpoint());
        self::assertSame('888', $result->getCustomerOrderId()->getEndpoint());
    }

    public function testStatisticReturnsAvailableCount(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('getValue')->willReturn('7');
        $this->controller->injectDb($db);

        $result = $this->controller->statistic();

        self::assertSame(7, $result->getAvailable());
        self::assertSame('PaymentController', $result->getControllerName());
    }

    public function testPullWithNoPaymentsReturnsEmptyArray(): void
    {
        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturn([]);
        $this->controller->injectDb($db);

        $filter = new QueryFilter();
        $result = $this->controller->pull($filter);

        self::assertSame([], $result);
    }

    public function testPullWithOnePaymentReturnsMappedPayment(): void
    {
        $paymentData = [[
            'id_order_payment' => '100',
            'id_order'         => '200',
            'date_add'         => '2023-06-15 10:00:00',
            'payment_method'   => 'paypal',
            'amount'           => '10.50',
            'transaction_id'   => 'txn-abc123',
            'order_reference'  => 'REF-001',
        ]];

        $db = $this->createMock(Db::class);
        $db->method('executeS')->willReturn($paymentData);
        $this->controller->injectDb($db);

        $filter = new QueryFilter();
        $result = $this->controller->pull($filter);

        self::assertCount(1, $result);
        self::assertInstanceOf(JtlPayment::class, $result[0]);
        self::assertSame('100', $result[0]->getId()->getEndpoint());
    }
}

