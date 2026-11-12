<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\SpecificController;

use Feature;
use FeatureValue;
use Jtl\Connector\Core\Definition\IdentityType;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\Specific;
use Jtl\Connector\Core\Model\SpecificI18n as JtlSpecificI18n;
use Jtl\Connector\Core\Model\SpecificValue as JtlSpecificValue;
use Jtl\Connector\Core\Model\SpecificValueI18n as JtlSpecificValueI18n;
use jtl\Connector\Presta\Mapper\PrimaryKeyMapper;
use ObjectModel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\Support\AfterHook;
use Tests\Support\Controller\TestableSpecificController;

final class PushTest extends TestCase
{
    private TestableSpecificController $controller;

    private PrimaryKeyMapper&MockObject $mapper;

    protected function setUp(): void
    {
        Feature::resetMock();
        FeatureValue::resetMock();
        ObjectModel::resetObjectModelMock();
        ObjectModel::$mockNextInsertId = 20;
        $this->controller              = new TestableSpecificController();
        $this->mapper                  = $this->createMock(PrimaryKeyMapper::class);
        $this->controller->injectMapper($this->mapper);
    }

    protected function tearDown(): void
    {
        Feature::resetMock();
        FeatureValue::resetMock();
        ObjectModel::resetObjectModelMock();
    }

    private function newSpecificWithNewValue(): Specific
    {
        return (new Specific())
            ->setId(new Identity('', 6001))
            ->setI18ns((new JtlSpecificI18n())->setName('Material')->setLanguageIso('eng'))
            ->setValues(
                (new JtlSpecificValue())
                    ->setId(new Identity('', 6002))
                    ->setI18ns((new JtlSpecificValueI18n())->setValue('Cotton')->setLanguageIso('eng'))
            );
    }

    public function testPushExistingSpecificThrowsWhenUpdateFails(): void
    {
        Feature::$mockUpdateResult = false;

        $i18n = (new JtlSpecificI18n())
            ->setName('Size')
            ->setLanguageIso('eng');

        $jtlSpecific = (new Specific())
            ->setId(new Identity('7'))
            ->setI18ns($i18n);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error updating specific: Size');

        $this->controller->push($jtlSpecific);
    }

    public function testPushNewSpecificThrowsWhenAddFails(): void
    {
        Feature::$mockAddResult = false;

        $i18n = (new JtlSpecificI18n())
            ->setName('Material')
            ->setLanguageIso('eng');

        $jtlSpecific = (new Specific())
            ->setId(new Identity(''))
            ->setI18ns($i18n);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error uploading specific Material');

        $this->controller->push($jtlSpecific);
    }

    public function testPushNewSpecificWithValuesReturnsModel(): void
    {
        $i18n = (new JtlSpecificI18n())
            ->setName('Material')
            ->setLanguageIso('eng');

        $valueI18n = (new JtlSpecificValueI18n())
            ->setValue('Cotton')
            ->setLanguageIso('eng');

        $value = (new JtlSpecificValue())
            ->setId(new Identity(''))
            ->setI18ns($valueI18n);

        $jtlSpecific = (new Specific())
            ->setId(new Identity(''))
            ->setI18ns($i18n)
            ->setValues($value);

        [$result] = $this->controller->push($jtlSpecific);

        self::assertSame($jtlSpecific, $result);
    }

    public function testPushExistingSpecificReturnsModel(): void
    {
        $i18n = (new JtlSpecificI18n())
            ->setName('Size')
            ->setLanguageIso('eng');

        $jtlSpecific = (new Specific())
            ->setId(new Identity('7'))
            ->setI18ns($i18n);

        // ObjectModel::update() returns true in stub
        [$result] = $this->controller->push($jtlSpecific);

        self::assertSame($jtlSpecific, $result);
    }

    public function testPushExistingSpecificWithValuesReturnsModel(): void
    {
        $i18n = (new JtlSpecificI18n())
            ->setName('Color')
            ->setLanguageIso('eng');

        $valueI18n = (new JtlSpecificValueI18n())
            ->setValue('Red')
            ->setLanguageIso('eng');

        $value = (new JtlSpecificValue())
            ->setId(new Identity(''))
            ->setI18ns($valueI18n);

        $jtlSpecific = (new Specific())
            ->setId(new Identity('7'))
            ->setI18ns($i18n)
            ->setValues($value);

        [$result] = $this->controller->push($jtlSpecific);

        self::assertSame($jtlSpecific, $result);
    }

    public function testPushNewSpecificReturnsModel(): void
    {
        $i18n = (new JtlSpecificI18n())
            ->setName('Material')
            ->setLanguageIso('eng');

        $jtlSpecific = (new Specific())
            ->setId(new Identity(''))
            ->setI18ns($i18n);

        // ObjectModel::add() returns true in stub
        [$result] = $this->controller->push($jtlSpecific);

        self::assertSame($jtlSpecific, $result);
    }

    public function testNewSpecificAndItsNewValuesAreLinked(): void
    {
        $this->mapper->expects(self::exactly(2))
            ->method('save')
            ->willReturnCallback(static function (int $type, string $endpointId, int $hostId): bool {
                self::assertContains([$type, $endpointId, $hostId], [
                    [IdentityType::SPECIFIC, '20', 6001],
                    [IdentityType::SPECIFIC_VALUE, '21', 6002],
                ]);
                return true;
            });

        [$result] = $this->controller->push($this->newSpecificWithNewValue());

        self::assertSame('20', $result->getId()->getEndpoint());
        self::assertSame('21', $result->getValues()[0]->getId()->getEndpoint());
    }

    public function testNewSpecificIsLinkedWhenPsEventbusReadsTheStackInItsHook(): void
    {
        ObjectModel::$mockAfterHook = AfterHook::psEventbusErrorHandler();
        $this->mapper->expects(self::exactly(2))->method('save');

        [$result] = $this->controller->push($this->newSpecificWithNewValue());

        self::assertSame('20', $result->getId()->getEndpoint());
        self::assertSame('21', $result->getValues()[0]->getId()->getEndpoint());
    }

    public function testNewSpecificIsLinkedAndWarningsLoggedWhenAHookThrowsAfterTheInserts(): void
    {
        ObjectModel::$mockAfterHook = AfterHook::throwing(new \TypeError('hook failed'));
        $this->mapper->expects(self::exactly(2))->method('save');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('warning');
        $this->controller->injectLogger($logger);

        [$result] = $this->controller->push($this->newSpecificWithNewValue());

        self::assertSame('20', $result->getId()->getEndpoint());
        self::assertSame('21', $result->getValues()[0]->getId()->getEndpoint());
    }

    public function testExistingValueIsUpdatedOnTheValueItIsLinkedTo(): void
    {
        $this->mapper->expects(self::never())->method('save');
        $jtlSpecific = (new Specific())
            ->setId(new Identity('7', 6001))
            ->setI18ns((new JtlSpecificI18n())->setName('Color')->setLanguageIso('eng'))
            ->setValues(
                (new JtlSpecificValue())
                    ->setId(new Identity('42', 6002))
                    ->setI18ns((new JtlSpecificValueI18n())->setValue('Red')->setLanguageIso('eng'))
            );

        $this->controller->push($jtlSpecific);

        self::assertSame([42], FeatureValue::$mockUpdatedIds);
    }

    public function testValueWhoseLinkedValueWasDeletedInTheShopIsAddedAndLinkedAgain(): void
    {
        FeatureValue::$mockMissingIds = [42];
        $this->mapper->expects(self::once())->method('delete')->with(IdentityType::SPECIFIC_VALUE, '42', 6002);
        $this->mapper->expects(self::once())->method('save')->with(IdentityType::SPECIFIC_VALUE, '20', 6002);
        $jtlSpecific = (new Specific())
            ->setId(new Identity('7', 6001))
            ->setI18ns((new JtlSpecificI18n())->setName('Color')->setLanguageIso('eng'))
            ->setValues(
                (new JtlSpecificValue())
                    ->setId(new Identity('42', 6002))
                    ->setI18ns((new JtlSpecificValueI18n())->setValue('Red')->setLanguageIso('eng'))
            );

        [$result] = $this->controller->push($jtlSpecific);

        self::assertSame('20', $result->getValues()[0]->getId()->getEndpoint());
        self::assertSame([], FeatureValue::$mockUpdatedIds);
    }

    public function testSpecificDeletedInTheShopIsAddedAndLinkedAgainWithItsValues(): void
    {
        // PrestaShop deletes the values of a deleted feature too
        Feature::$mockMissingIds      = [7];
        FeatureValue::$mockMissingIds = [42];
        $deleted                      = [];
        $saved                        = [];
        $this->mapper->method('delete')->willReturnCallback(
            static function (int $type, ?string $endpointId = null, ?int $hostId = null) use (&$deleted): bool {
                $deleted[] = [$type, $endpointId, $hostId];
                return true;
            }
        );
        $this->mapper->method('save')->willReturnCallback(
            static function (int $type, string $endpointId, int $hostId) use (&$saved): bool {
                $saved[] = [$type, $endpointId, $hostId];
                return true;
            }
        );
        $jtlSpecific = (new Specific())
            ->setId(new Identity('7', 6001))
            ->setI18ns((new JtlSpecificI18n())->setName('Color')->setLanguageIso('eng'))
            ->setValues(
                (new JtlSpecificValue())
                    ->setId(new Identity('42', 6002))
                    ->setI18ns((new JtlSpecificValueI18n())->setValue('Red')->setLanguageIso('eng'))
            );

        [$result] = $this->controller->push($jtlSpecific);

        self::assertSame([[IdentityType::SPECIFIC, '7', 6001], [IdentityType::SPECIFIC_VALUE, '42', 6002]], $deleted);
        self::assertSame([[IdentityType::SPECIFIC, '20', 6001], [IdentityType::SPECIFIC_VALUE, '21', 6002]], $saved);
        self::assertSame('20', $result->getId()->getEndpoint());
        self::assertSame('21', $result->getValues()[0]->getId()->getEndpoint());
    }

    public function testUpdatesAndDeletesWithoutStackFramesThatPsEventbusCannotRead(): void
    {
        ObjectModel::$mockAfterHook = AfterHook::psEventbusErrorHandler();
        $jtlSpecific                = (new Specific())
            ->setId(new Identity('7', 6001))
            ->setI18ns((new JtlSpecificI18n())->setName('Size')->setLanguageIso('eng'));

        self::assertSame([$jtlSpecific], $this->controller->push($jtlSpecific));
        self::assertSame([$jtlSpecific], $this->controller->delete($jtlSpecific));
    }
}
