<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\SpecificController;

use Feature;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\Specific;
use Jtl\Connector\Core\Model\SpecificI18n as JtlSpecificI18n;
use PHPUnit\Framework\TestCase;
use Tests\Support\Controller\TestableSpecificController;

final class CreatePrestaSpecificTest extends TestCase
{
    private TestableSpecificController $controller;

    protected function setUp(): void
    {
        Feature::resetMock();
        $this->controller = new TestableSpecificController();
    }

    public function testCreatePrestaSpecificSetsTranslations(): void
    {
        $i18n = (new JtlSpecificI18n())
            ->setName('Color')
            ->setLanguageIso('eng');

        $jtlSpecific = (new Specific())
            ->setId(new Identity(''))
            ->setI18ns($i18n);

        $prestaSpecific = new Feature();
        $result         = $this->controller->exposeCreatePrestaSpecific($jtlSpecific, $prestaSpecific);

        self::assertInstanceOf(Feature::class, $result);
        // getPrestaLanguageIdFromIso('eng') returns 1 in stub
        self::assertSame('Color', $result->name[1]);
    }
}
