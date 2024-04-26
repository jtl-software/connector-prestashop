<?php

declare(strict_types=1);

namespace jtl\Connector\Presta\Utils;

use Jtl\Connector\Core\Definition\PaymentType;

class Utils
{
    /**
     * @param      $id
     * @param null $padValue
     *
     * @return array
     */
    public static function explodeProductEndpoint($id, $padValue = null)
    {
        return \array_pad(\explode('_', $id, 2), 2, $padValue);
    }

    /**
     * @param $id
     * @param $endpoint
     *
     * @return string
     */
    public static function joinProductEndpoint($id, $endpoint)
    {
        return \implode('_', [$id, $endpoint]);
    }

    /**
     * @param $module
     *
     * @return string
     */
    public static function mapPaymentModuleCode($module): string
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

    public static function stringToFloat(string $input): float
    {
        if (\is_float((float)$input)) {
            return (float)$input;
        }

        return (float)\str_replace(',', '.', $input);
    }
}
