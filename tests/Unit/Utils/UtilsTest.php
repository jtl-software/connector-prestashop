<?php

declare(strict_types=1);

namespace Tests\Unit\Utils;

use Jtl\Connector\Core\Definition\PaymentType;
use jtl\Connector\Presta\Utils\Utils;
use PHPUnit\Framework\TestCase;

/**
 * @covers \jtl\Connector\Presta\Utils\Utils
 */
class UtilsTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: mixed, 2: array{0: string, 1: mixed}}>
     */
    public static function explodeProductEndpointProvider(): array
    {
        return [
            'plain id without separator'   => ['64', null, ['64', null]],
            'product and combination id'   => ['64_5', null, ['64', '5']],
            'custom pad value is applied'  => ['64', '0', ['64', '0']],
            'only splits on first separ_'  => ['64_5_7', null, ['64', '5_7']],
            'empty string'                 => ['', null, ['', null]],
        ];
    }

    /**
     * @dataProvider explodeProductEndpointProvider
     *
     * @param string               $id
     * @param mixed                $padValue
     * @param array{0: string, 1: mixed} $expected
     *
     * @return void
     */
    public function testExplodeProductEndpoint(string $id, mixed $padValue, array $expected): void
    {
        $this->assertSame($expected, Utils::explodeProductEndpoint($id, $padValue));
    }

    /**
     * @return void
     */
    public function testJoinProductEndpoint(): void
    {
        $this->assertSame('64_5', Utils::joinProductEndpoint('64', '5'));
        $this->assertSame('64_', Utils::joinProductEndpoint('64', ''));
    }

    /**
     * @return void
     */
    public function testJoinIsInverseOfExplode(): void
    {
        [$productId, $combinationId] = Utils::explodeProductEndpoint('64_5');

        $this->assertSame('64_5', Utils::joinProductEndpoint($productId, (string)$combinationId));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function paymentModuleProvider(): array
    {
        return [
            'wire payment'          => ['ps_wirepayment', PaymentType::BANK_TRANSFER],
            'cash on delivery'      => ['ps_cashonedlivery', PaymentType::CASH_ON_DELIVERY],
            'klarna official'       => ['klarnapaymentsofficial', PaymentType::KLARNA],
            'paypal'                => ['paypal', PaymentType::PAYPAL],
            'mollie exact'          => ['mollie', PaymentType::MOLLIE],
            'mollie sub module'     => ['mollie_bancontact', PaymentType::MOLLIE],
            'unknown module as-is'  => ['some_unknown_module', 'some_unknown_module'],
        ];
    }

    /**
     * @dataProvider paymentModuleProvider
     *
     * @param string $module
     * @param string $expected
     *
     * @return void
     */
    public function testMapPaymentModuleCode(string $module, string $expected): void
    {
        $this->assertSame($expected, Utils::mapPaymentModuleCode($module));
    }

    /**
     * @return array<string, array{0: string, 1: float}>
     */
    public static function stringToFloatProvider(): array
    {
        return [
            'numeric dot notation'      => ['1.5', 1.5],
            'german comma notation'     => ['1,5', 1.5],
            'integer string'            => ['1234', 1234.0],
            'trailing zero comma'       => ['1,50', 1.5],
            'zero'                      => ['0', 0.0],
            'negative comma notation'   => ['-2,25', -2.25],
        ];
    }

    /**
     * @dataProvider stringToFloatProvider
     *
     * @param string $input
     * @param float  $expected
     *
     * @return void
     */
    public function testStringToFloat(string $input, float $expected): void
    {
        $this->assertSame($expected, Utils::stringToFloat($input));
    }
}
