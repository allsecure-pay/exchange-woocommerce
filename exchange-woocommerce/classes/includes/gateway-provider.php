<?php

final class WC_Payment_Gateway_Provider
{
    public static function paymentMethods(): array
    {
        return [
            'WC_Payment_Gateway_Creditcard',
            // 'WC_Payment_Gateway_Creditcard_Amex',
            // 'WC_Payment_Gateway_Creditcard_Diners',
            // 'WC_Payment_Gateway_Creditcard_Discover',
            // 'WC_Payment_Gateway_Creditcard_Jcb',
            // 'WC_Payment_Gateway_Creditcard_Maestro',
            // 'WC_Payment_Gateway_Creditcard_Mastercard',
            // 'WC_Payment_Gateway_Creditcard_Unionpay',
            // 'WC_Payment_Gateway_Creditcard_Visa',
        ];
    }

    public static function autoloadClient()
    {
        require_once PAYMENTGATEWAY_EXTENSION_BASEDIR . 'classes/vendor/autoload.php';
    }

    public static function setupClient()
    {
        \PaymentGateway\Client\Client::setApiUrl(PAYMENTGATEWAY_EXTENSION_URL);
    }
}
