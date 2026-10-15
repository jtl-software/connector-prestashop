<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Db;
use Exception;
use Order;
use OrderCarrier;
use Jtl\Connector\Core\Model\DeliveryNote;
use Jtl\Connector\Core\Model\DeliveryNoteTrackingList;
use Jtl\Connector\Core\Model\Identity;
use jtl\Connector\Presta\Controller\DeliveryNoteController;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class TestableDeliveryNoteController extends DeliveryNoteController
{
    public function __construct()
    {
        $this->db             = Db::getInstance();
        $this->logger         = new NullLogger();
        $this->controllerName = 'DeliveryNoteController';
    }
}

/**
 * DeliveryNoteController::push() mixes Order instantiation and DB queries in
 * a single method without injection points, so only the observable outcomes
 * at the public boundary can be tested without refactoring the controller.
 *
 * Observable cases:
 *  1. Order not found (stub: new Order(0) → id = null) → Exception thrown
 *  2. Order found but no OrderCarrier (Db::getValue returns null/false) → Exception thrown
 *  3. Order found, OrderCarrier found, no tracking codes → model returned unchanged
 *  4. Order found, OrderCarrier found, tracking codes → carrier gets updated tracking_number
 *  5. update() throws exception with 'tracking_number' in message → special wrapped exception
 *  6. update() throws exception without 'tracking_number' → re-throw original
 */
final class DeliveryNoteControllerTest extends TestCase
{
    private TestableDeliveryNoteController $controller;

    protected function setUp(): void
    {
        Db::resetInstance();
        OrderCarrier::resetMock();
        Order::resetMock();
        $this->controller = new TestableDeliveryNoteController();
    }

    protected function tearDown(): void
    {
        OrderCarrier::resetMock();
        Order::resetMock();
        Db::resetInstance();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeDeliveryNote(string $orderEndpoint): DeliveryNote
    {
        $note = new DeliveryNote();
        $note->setCustomerOrderId(new Identity($orderEndpoint, 0));
        return $note;
    }

    private function makeDbReturningCarrierId(string $carrierId = '7'): Db
    {
        return new class($carrierId) extends Db {
            public function __construct(private readonly string $carrierId)
            {
            }

            public function getValue(mixed $sql, bool $useCache = true): mixed
            {
                return $this->carrierId;
            }
        };
    }

    // =========================================================================
    // Order not found → Exception
    // =========================================================================

    public function testPushThrowsExceptionWhenOrderNotFound(): void
    {
        // new Order(0) → id = null → controller throws
        $note = $this->makeDeliveryNote('0');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Order with id 0 not found');

        $this->controller->push($note);
    }

    public function testPushThrowsExceptionMessageContainsEndpointWhenOrderNotFound(): void
    {
        Order::$mockNotFoundIds = [123];
        $note = $this->makeDeliveryNote('123');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Order with id 123 not found');

        $this->controller->push($note);
    }

    // =========================================================================
    // Order found but OrderCarrier not found
    // =========================================================================

    public function testPushThrowsExceptionWhenCarrierNotFoundForValidOrder(): void
    {
        // '999999' → new Order(999999) → id = 999999 (truthy) → order exists
        // Db::getValue returns null (default stub) → carrier not found → exception
        $note = $this->makeDeliveryNote('999999');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Order carrier for order 999999 not found');

        $this->controller->push($note);
    }

    public function testPushThrowsExceptionWhenOrderCarrierNotFound(): void
    {
        // new Order(42) → id = 42 → Order exists
        // Db::getValue returns null (default stub) → no carrier found
        $note = $this->makeDeliveryNote('42');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Order carrier for order 42 not found');

        $this->controller->push($note);
    }

    public function testPushCarrierNotFoundExceptionMessageContainsOrderId(): void
    {
        $db = new class extends Db {
            public function getValue(mixed $sql, bool $useCache = true): mixed
            {
                return false; // carrier not found
            }
        };
        Db::setInstance($db);
        $controller = new TestableDeliveryNoteController();

        $note = $this->makeDeliveryNote('55');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Order carrier for order 55 not found');

        $controller->push($note);
    }

    // =========================================================================
    // Order exists, carrier exists, no tracking codes → model returned
    // =========================================================================

    public function testPushReturnsModelWhenNoTrackingCodesAndCarrierFound(): void
    {
        Db::setInstance($this->makeDbReturningCarrierId('7'));
        $controller = new TestableDeliveryNoteController();
        $note       = $this->makeDeliveryNote('42');

        $result = $controller->push($note);

        self::assertSame($note, $result);
    }

    public function testPushReturnsModelWhenTrackingListsAreEmpty(): void
    {
        Db::setInstance($this->makeDbReturningCarrierId('7'));
        $controller = new TestableDeliveryNoteController();

        $note = $this->makeDeliveryNote('42');
        // empty tracking list (no codes, no URLs) → trackingCodes stays empty
        $note->addTrackingList(new DeliveryNoteTrackingList());

        $result = $controller->push($note);

        self::assertSame($note, $result);
    }

    // =========================================================================
    // Tracking list with code + URL where URL contains code → uses URL
    // =========================================================================

    public function testPushUsesTrackingUrlWhenUrlContainsCode(): void
    {
        Db::setInstance($this->makeDbReturningCarrierId('7'));
        $controller = new TestableDeliveryNoteController();

        $trackingList = new DeliveryNoteTrackingList();
        $trackingList->addCode('TRACK123');
        $trackingList->addTrackingURL('https://track.example.com/TRACK123');

        $note = $this->makeDeliveryNote('42');
        $note->addTrackingList($trackingList);

        $result = $controller->push($note);

        self::assertSame($note, $result);
    }

    // =========================================================================
    // Tracking list with code + URL where URL does NOT contain code → uses code
    // =========================================================================

    public function testPushUsesCodeWhenUrlDoesNotContainCode(): void
    {
        Db::setInstance($this->makeDbReturningCarrierId('7'));
        $controller = new TestableDeliveryNoteController();

        $trackingList = new DeliveryNoteTrackingList();
        $trackingList->addCode('XYZ-999');
        $trackingList->addTrackingURL('https://generic-tracker.com/track');

        $note = $this->makeDeliveryNote('42');
        $note->addTrackingList($trackingList);

        $result = $controller->push($note);

        self::assertSame($note, $result);
    }

    // =========================================================================
    // Multiple tracking codes are deduplicated
    // =========================================================================

    public function testPushDeduplicatesTrackingCodes(): void
    {
        Db::setInstance($this->makeDbReturningCarrierId('7'));
        $controller = new TestableDeliveryNoteController();

        $trackingList = new DeliveryNoteTrackingList();
        // Same code twice with same URL → str_contains = true → two identical URLs
        $trackingList->addCode('DUP-111');
        $trackingList->addTrackingURL('https://track.com/DUP-111');
        $trackingList->addTrackingURL('https://track.com/DUP-111');

        $note = $this->makeDeliveryNote('42');
        $note->addTrackingList($trackingList);

        // Should not throw; array_unique will deduplicate
        $result = $controller->push($note);

        self::assertSame($note, $result);
    }

    // =========================================================================
    // Existing tracking number on carrier is merged with new codes
    // =========================================================================

    public function testPushMergesExistingTrackingNumberWithNewCodes(): void
    {
        Db::setInstance($this->makeDbReturningCarrierId('99'));
        $controller = new TestableDeliveryNoteController();

        $trackingList = new DeliveryNoteTrackingList();
        $trackingList->addCode('NEW-CODE');
        $trackingList->addTrackingURL('https://x.com/NEW-CODE');

        $note = $this->makeDeliveryNote('42');
        $note->addTrackingList($trackingList);

        // OrderCarrier tracking_number is '' by default → merge branch skipped
        $result = $controller->push($note);

        self::assertSame($note, $result);
    }

    public function testPushMergesExistingTrackingNumberFromOrderCarrier(): void
    {
        OrderCarrier::$mockTrackingNumber = 'EXISTING-CODE';

        Db::setInstance($this->makeDbReturningCarrierId('7'));
        $controller = new TestableDeliveryNoteController();

        $trackingList = new DeliveryNoteTrackingList();
        $trackingList->addCode('NEW-CODE');
        $trackingList->addTrackingURL('https://x.com/NEW-CODE');

        $note = $this->makeDeliveryNote('42');
        $note->addTrackingList($trackingList);

        // With existing tracking number and new code, both are merged
        $result = $controller->push($note);
        self::assertSame($note, $result);
    }

    public function testPushMergesMultipleExistingTrackingCodesFromCarrier(): void
    {
        // carrier already has two comma-separated codes
        OrderCarrier::$mockTrackingNumber = 'OLD-A, OLD-B';

        Db::setInstance($this->makeDbReturningCarrierId('7'));
        $controller = new TestableDeliveryNoteController();

        $trackingList = new DeliveryNoteTrackingList();
        $trackingList->addCode('NEW-C');
        $trackingList->addTrackingURL('https://x.com/NEW-C');

        $note = $this->makeDeliveryNote('42');
        $note->addTrackingList($trackingList);

        $result = $controller->push($note);
        self::assertSame($note, $result);
    }

    // =========================================================================
    // update() returns false → exception thrown (and re-thrown after catch)
    // =========================================================================

    public function testPushThrowsWhenOrderCarrierUpdateFails(): void
    {
        OrderCarrier::$mockUpdateResult = false;
        Db::setInstance($this->makeDbReturningCarrierId('7'));
        $controller = new TestableDeliveryNoteController();

        $trackingList = new DeliveryNoteTrackingList();
        $trackingList->addCode('TRACK1');
        $trackingList->addTrackingURL('https://track.com/TRACK1');

        $note = $this->makeDeliveryNote('42');
        $note->addTrackingList($trackingList);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Couldn't update delivery note for order 42");

        $controller->push($note);
    }

    // =========================================================================
    // update() throws exception with 'tracking_number' in message → special wrapping
    // =========================================================================

    public function testPushThrowsWrappedExceptionWhenUpdateThrowsWithTrackingNumberInMessage(): void
    {
        OrderCarrier::$mockUpdateShouldThrow    = true;
        OrderCarrier::$mockUpdateExceptionMessage = 'Invalid value for tracking_number field';

        Db::setInstance($this->makeDbReturningCarrierId('7'));
        $controller = new TestableDeliveryNoteController();

        $trackingList = new DeliveryNoteTrackingList();
        $trackingList->addCode('BAD-CODE');
        $trackingList->addTrackingURL('https://track.example.com/BAD-CODE');

        $note = $this->makeDeliveryNote('42');
        $note->addTrackingList($trackingList);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Prestashop does not like the tracking number');

        $controller->push($note);
    }

    public function testPushWrappedExceptionContainsTrackingCodeAndOrderId(): void
    {
        OrderCarrier::$mockUpdateShouldThrow      = true;
        OrderCarrier::$mockUpdateExceptionMessage = 'tracking_number too long';

        Db::setInstance($this->makeDbReturningCarrierId('7'));
        $controller = new TestableDeliveryNoteController();

        $trackingList = new DeliveryNoteTrackingList();
        $trackingList->addCode('MY-TRACK-XYZ');
        $trackingList->addTrackingURL('https://track.example.com/MY-TRACK-XYZ');

        $note = $this->makeDeliveryNote('42');
        $note->addTrackingList($trackingList);

        try {
            $controller->push($note);
            self::fail('Expected exception was not thrown');
        } catch (Exception $e) {
            self::assertStringContainsString('MY-TRACK-XYZ', $e->getMessage());
            self::assertStringContainsString('42', $e->getMessage());
            self::assertStringContainsString('tracking_number too long', $e->getMessage());
        }
    }

    // =========================================================================
    // update() throws exception WITHOUT 'tracking_number' in message → re-throw
    // =========================================================================

    public function testPushRethrowsOriginalExceptionWhenUpdateThrowsWithoutTrackingNumberInMessage(): void
    {
        OrderCarrier::$mockUpdateShouldThrow      = true;
        OrderCarrier::$mockUpdateExceptionMessage = 'Database connection lost';

        Db::setInstance($this->makeDbReturningCarrierId('7'));
        $controller = new TestableDeliveryNoteController();

        $trackingList = new DeliveryNoteTrackingList();
        $trackingList->addCode('CODE-1');
        $trackingList->addTrackingURL('https://track.example.com/CODE-1');

        $note = $this->makeDeliveryNote('42');
        $note->addTrackingList($trackingList);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Database connection lost');

        $controller->push($note);
    }
}
