<?php

declare(strict_types=1);

namespace Tests\Unit\Utils;

use Jtl\Connector\Core\Definition\PaymentType;
use jtl\Connector\Presta\Utils\Utils;
use PHPUnit\Framework\TestCase;

final class UtilsTest extends TestCase
{
    // -------------------------------------------------------------------------
    // explodeProductEndpoint
    // -------------------------------------------------------------------------

    public function testExplodeProductEndpointWithUnderscoreSeparatedIdReturnsBothParts(): void
    {
        self::assertSame(['123', '456'], Utils::explodeProductEndpoint('123_456'));
    }

    public function testExplodeProductEndpointWithNoUnderscorePadsWithNull(): void
    {
        self::assertSame(['123', null], Utils::explodeProductEndpoint('123'));
    }

    public function testExplodeProductEndpointWithCustomPadValueUsesProvidedDefault(): void
    {
        self::assertSame(['123', 0], Utils::explodeProductEndpoint('123', 0));
    }

    public function testExplodeProductEndpointRespectsLimitOfTwoAndKeepsRemainingInSecondElement(): void
    {
        // explode with limit 2 must not split further – 'b_c' stays together
        self::assertSame(['a', 'b_c'], Utils::explodeProductEndpoint('a_b_c'));
    }

    public function testExplodeProductEndpointWithEmptyStringReturnsTwoElementsFirstEmpty(): void
    {
        self::assertSame(['', null], Utils::explodeProductEndpoint(''));
    }

    // -------------------------------------------------------------------------
    // joinProductEndpoint
    // -------------------------------------------------------------------------

    public function testJoinProductEndpointCombinesIdAndEndpointWithUnderscore(): void
    {
        self::assertSame('123_456', Utils::joinProductEndpoint('123', '456'));
    }

    public function testJoinProductEndpointWithEmptyStringsReturnsUnderscore(): void
    {
        self::assertSame('_', Utils::joinProductEndpoint('', ''));
    }

    // -------------------------------------------------------------------------
    // mapPaymentModuleCode
    // -------------------------------------------------------------------------

    public function testMapPaymentModuleCodeReturnsBankTransferForWirePayment(): void
    {
        self::assertSame(PaymentType::BANK_TRANSFER, Utils::mapPaymentModuleCode('ps_wirepayment'));
    }

    public function testMapPaymentModuleCodeReturnsCashOnDeliveryForCashOnDeliveryModule(): void
    {
        self::assertSame(PaymentType::CASH_ON_DELIVERY, Utils::mapPaymentModuleCode('ps_cashonedlivery'));
    }

    public function testMapPaymentModuleCodeReturnsKlarnaForKlarnaModule(): void
    {
        self::assertSame(PaymentType::KLARNA, Utils::mapPaymentModuleCode('klarnapaymentsofficial'));
    }

    public function testMapPaymentModuleCodeReturnsPaypalForPaypalModule(): void
    {
        self::assertSame(PaymentType::PAYPAL, Utils::mapPaymentModuleCode('paypal'));
    }

    public function testMapPaymentModuleCodeReturnsMollieWhenModuleNameContainsMollieAsSubstring(): void
    {
        self::assertSame(PaymentType::MOLLIE, Utils::mapPaymentModuleCode('mollie_something'));
    }

    public function testMapPaymentModuleCodeReturnsMollieForExactMollieName(): void
    {
        self::assertSame(PaymentType::MOLLIE, Utils::mapPaymentModuleCode('mollie'));
    }

    public function testMapPaymentModuleCodeReturnsMollieWhenMollieIsPrefixOfLongerName(): void
    {
        self::assertSame(PaymentType::MOLLIE, Utils::mapPaymentModuleCode('molliepayments'));
    }

    public function testMapPaymentModuleCodeReturnsOriginalModuleStringForUnknownModule(): void
    {
        self::assertSame('unknown_payment', Utils::mapPaymentModuleCode('unknown_payment'));
    }

    public function testMapPaymentModuleCodeReturnsOriginalStringForEmptyModule(): void
    {
        self::assertSame('', Utils::mapPaymentModuleCode(''));
    }

    // -------------------------------------------------------------------------
    // stringToFloat
    // -------------------------------------------------------------------------

    public function testStringToFloatConvertsDecimalPointStringToFloat(): void
    {
        self::assertSame(1.5, Utils::stringToFloat('1.5'));
    }

    public function testStringToFloatConvertsZeroStringToZeroFloat(): void
    {
        self::assertSame(0.0, Utils::stringToFloat('0'));
    }

    public function testStringToFloatConvertsCommaDecimalStringToFloat(): void
    {
        self::assertSame(1.5, Utils::stringToFloat('1,5'));
    }

    public function testStringToFloatConvertsNegativeCommaDecimalStringToFloat(): void
    {
        self::assertSame(-1.5, Utils::stringToFloat('-1,5'));
    }

    public function testStringToFloatConvertsNonNumericNonCommaStringToZero(): void
    {
        // 'abc' is not numeric; str_replace changes nothing; (float)'abc' = 0.0
        self::assertSame(0.0, Utils::stringToFloat('abc'));
    }

    public function testStringToFloatConvertsIntegerStringViaNumericBranch(): void
    {
        self::assertSame(42.0, Utils::stringToFloat('42'));
    }

    public function testStringToFloatConvertsNegativeDecimalPointStringViaNumericBranch(): void
    {
        self::assertSame(-3.14, Utils::stringToFloat('-3.14'));
    }

    public function testStringToFloatConvertsScientificNotationStringViaNumericBranch(): void
    {
        self::assertSame(1.0e2, Utils::stringToFloat('1e2'));
    }
}
