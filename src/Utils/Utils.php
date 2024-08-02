<?php

declare(strict_types=1);

namespace jtl\Connector\Presta\Utils;

use Jtl\Connector\Core\Definition\PaymentType;

class Utils
{
    /**
     * @template T of scalar|null
     * @param string $id
     * @param T      $padValue
     *
     * @return array{0: string, 1: string|T}
     */
    public static function explodeProductEndpoint(string $id, mixed $padValue = null): array // phpcs:ignore
    {
        /** @var array{0: string, 1: T} $result */
        $result = \array_pad(\explode('_', $id, 2), 2, $padValue);
        return $result;
    }

    /**
     * @param string $id
     * @param string $endpoint
     *
     * @return string
     */
    public static function joinProductEndpoint(string $id, string $endpoint): string
    {
        return \implode('_', [$id, $endpoint]);
    }

    /**
     * @param string $module
     *
     * @return string
     */
    public static function mapPaymentModuleCode(string $module): string
    {
        // for payments where we don't know the actual module name
        if (\str_contains($module, 'mollie')) {
            return PaymentType::MOLLIE;
        }
        // for payments where we know the actual module name
        return match ($module) {
            'ps_wirepayment' => PaymentType::BANK_TRANSFER,
            'ps_cashonedlivery' => PaymentType::CASH_ON_DELIVERY,
            'klarnapaymentsofficial' => PaymentType::KLARNA,
            'paypal' => PaymentType::PAYPAL,
            default => $module,
        };
    }

    /**
     * @param string $input
     *
     * @return float
     */
    public static function stringToFloat(string $input): float
    {
        if (\is_numeric($input)) {
            return (float)$input;
        }

        return (float)\str_replace(',', '.', $input);
    }
}
