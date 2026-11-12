<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\SpecificController;

use FeatureValue;
use Jtl\Connector\Core\Model\SpecificValueI18n as JtlSpecificValueI18n;
use PHPUnit\Framework\TestCase;
use Tests\Support\Controller\TestableSpecificController;

final class CreatePrestaSpecificValueI18nsTest extends TestCase
{
    private TestableSpecificController $controller;

    protected function setUp(): void
    {
        FeatureValue::resetMock();
        $this->controller = new TestableSpecificController();
    }

    public function testCreatePrestaSpecificValueI18nsSetsValueForLang(): void
    {
        $i18n = (new JtlSpecificValueI18n())
            ->setValue('Blue')
            ->setLanguageIso('eng');

        $prestaSpecificValue = new FeatureValue();
        $result              = $this->controller->exposeCreatePrestaSpecificValueI18ns($i18n, $prestaSpecificValue);

        // getPrestaLanguageIdFromIso('eng') returns 1 in stub
        self::assertSame('Blue', $result->value[1]);
    }
}
