<?php

declare(strict_types=1);

namespace jtl\Connector\Presta\Utils;

use Jtl\Connector\Core\Definition\PaymentType;

class Utils
{
    /**
     * @param $id
     * @param null $padValue
     * @return array
     */
    public static function explodeProductEndpoint($id, $padValue = null)
    {
        return \array_pad(\explode('_', $id, 2), 2, $padValue);
    }

    /**
     * @param $module
     * @return string
     */
    public static function mapPaymentModuleCode($module): string
    {
        $mappedPaymentModuleCode = '';

        switch ($module) {
            case 'ps_wirepayment':
                $mappedPaymentModuleCode = PaymentType::BANK_TRANSFER;
                break;
            case 'ps_cashondelivery':
                $mappedPaymentModuleCode = PaymentType::CASH_ON_DELIVERY;
                break;
            case 'paypal':
                $mappedPaymentModuleCode = PaymentType::PAYPAL;
                break;
            case 'klarnapaymentsofficial':
                $mappedPaymentModuleCode = PaymentType::KLARNA;
                break;
        }

        return $mappedPaymentModuleCode;
    }

    public static function stringToFloat(string $input): float
    {
        if (\is_float((float)$input)) {
            return (float)$input;
        }

        return (float)\str_replace(',', '.', $input);
    }
}
