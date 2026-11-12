<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\AbstractController;

use Jtl\Connector\Core\Model\AbstractModel;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\Manufacturer as JtlManufacturer;
use ObjectModel;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\Support\AfterHook;
use Tests\Support\Controller\ConcreteAbstractController;

/**
 * forEachModel() and addPrestaObject(), the helpers every push uses (CO-3735).
 */
final class PushHelpersTest extends TestCase
{
    private ConcreteAbstractController $controller;

    protected function setUp(): void
    {
        ObjectModel::resetObjectModelMock();
        $this->controller = new ConcreteAbstractController();
    }

    protected function tearDown(): void
    {
        ObjectModel::resetObjectModelMock();
    }

    public function testForEachModelReturnsTheHandlerResultsInOrder(): void
    {
        $first  = (new JtlManufacturer())->setId(new Identity('', 1));
        $second = (new JtlManufacturer())->setId(new Identity('', 2));
        $seen   = [];

        $result = $this->controller->callForEachModel(
            [$first, $second],
            static function (AbstractModel $model) use (&$seen): AbstractModel {
                $seen[] = $model;
                return $model;
            }
        );

        self::assertSame([$first, $second], $seen);
        self::assertSame([$first, $second], $result);
    }

    public function testForEachModelCallsTheHandlerWithoutStackFramesThatLackAFile(): void
    {
        $object = new ObjectModel();
        ObjectModel::$mockAfterHook = AfterHook::psEventbusErrorHandler();

        $this->controller->callForEachModel(
            [new JtlManufacturer()],
            static function (AbstractModel $model) use ($object): AbstractModel {
                $object->add();
                return $model;
            }
        );

        $this->addToAssertionCount(1);
    }

    public function testThePsEventbusSimulationFailsForACallbackCalledByAnInternalFunction(): void
    {
        // negative control for the test above: forEachModel() as array_map() would fail it
        ObjectModel::$mockAfterHook = AfterHook::psEventbusErrorHandler();

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('$file');

        \array_map(static fn(int $i): bool => (new ObjectModel())->add(), [1]);
    }

    public function testAddPrestaObjectValidatesTheMultilangFieldsBeforeTheInsert(): void
    {
        // before PrestaShop 9.1, add() validates them only after the INSERT, when the object already has an id
        ObjectModel::$mockNextInsertId        = 5;
        $error                                = new \PrestaShopException('Property ObjectModel->name is not valid');
        ObjectModel::$mockLangValidationError = $error;
        $object                               = new ObjectModel();

        try {
            $this->controller->callAddPrestaObject($object);
            self::fail('Expected the validation error');
        } catch (\PrestaShopException $e) {
            self::assertSame($error, $e);
        }

        self::assertNull($object->id, 'nothing may be inserted');
    }

    public function testAddPrestaObjectReturnsTrueWhenAddSucceeds(): void
    {
        ObjectModel::$mockNextInsertId = 5;
        $object                        = new ObjectModel();

        self::assertTrue($this->controller->callAddPrestaObject($object));
        self::assertSame(5, $object->id);
    }

    public function testAddPrestaObjectReturnsFalseWhenAddFails(): void
    {
        $object = new class extends ObjectModel {
            public function add(bool $autoDate = true, bool $nullValues = false): bool
            {
                return false;
            }
        };

        self::assertFalse($this->controller->callAddPrestaObject($object));
    }

    public function testAddPrestaObjectRemovesAnObjectThatPrestaShopSavedOnlyInPart(): void
    {
        // add() returns false after the INSERT, e.g. when a _lang or _shop INSERT failed
        $object = new class extends ObjectModel {
            public bool $deleted = false;

            public function add(bool $autoDate = true, bool $nullValues = false): bool
            {
                $this->id = 5;
                return false;
            }

            public function delete(): bool
            {
                $this->deleted = true;
                return true;
            }
        };

        self::assertFalse($this->controller->callAddPrestaObject($object));
        self::assertTrue($object->deleted);
    }

    public function testAddPrestaObjectLogsWhenItCannotRemoveAPartlySavedObject(): void
    {
        $object = new class extends ObjectModel {
            public function add(bool $autoDate = true, bool $nullValues = false): bool
            {
                $this->id = 5;
                return false;
            }

            public function delete(): bool
            {
                throw new \PrestaShopException('delete failed');
            }
        };
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(self::stringContains('Could not remove the partly saved'), self::anything());
        $this->controller->injectLogger($logger);

        self::assertFalse($this->controller->callAddPrestaObject($object));
    }

    public function testAddPrestaObjectCountsAnErrorAfterTheInsertAsAddedAndLogsAWarning(): void
    {
        ObjectModel::$mockNextInsertId = 5;
        $error                         = new \TypeError('hook failed');
        ObjectModel::$mockAfterHook    = AfterHook::throwing($error);
        $logger                        = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                self::callback(
                    static fn(string $message): bool => \str_contains($message, 'ObjectModel 5')
                        && \str_contains($message, 'TypeError: hook failed')
                ),
                ['exception' => $error]
            );
        $this->controller->injectLogger($logger);
        $object = new ObjectModel();

        self::assertTrue($this->controller->callAddPrestaObject($object));
        self::assertSame(5, $object->id);
    }

    public function testAddPrestaObjectRethrowsAnErrorBeforeTheInsert(): void
    {
        $error                      = new RuntimeException('insert failed');
        ObjectModel::$mockAfterHook = AfterHook::throwing($error);

        $this->expectExceptionObject($error);

        // no $mockNextInsertId: the object gets no id, as when PrestaShop throws before or during the INSERT
        $this->controller->callAddPrestaObject(new ObjectModel());
    }

    public function testAddPrestaObjectRethrowsForAnObjectThatAlreadyHadAnId(): void
    {
        $error                      = new RuntimeException('hook failed');
        ObjectModel::$mockAfterHook = AfterHook::throwing($error);

        $this->expectExceptionObject($error);

        $this->controller->callAddPrestaObject(new ObjectModel(9));
    }
}
