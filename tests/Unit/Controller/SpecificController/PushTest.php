<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\SpecificController;

use Feature;
use FeatureValue;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\Specific;
use Jtl\Connector\Core\Model\SpecificI18n as JtlSpecificI18n;
use Jtl\Connector\Core\Model\SpecificValue as JtlSpecificValue;
use Jtl\Connector\Core\Model\SpecificValueI18n as JtlSpecificValueI18n;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Controller\TestableSpecificController;

final class PushTest extends TestCase
{
    private TestableSpecificController $controller;

    protected function setUp(): void
    {
        Feature::resetMock();
        FeatureValue::resetMock();
        $this->controller = new TestableSpecificController();
    }

    protected function tearDown(): void
    {
        Feature::resetMock();
        FeatureValue::resetMock();
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

        $result = $this->controller->push($jtlSpecific);

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
        $result = $this->controller->push($jtlSpecific);

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

        $result = $this->controller->push($jtlSpecific);

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
        $result = $this->controller->push($jtlSpecific);

        self::assertSame($jtlSpecific, $result);
    }
}
