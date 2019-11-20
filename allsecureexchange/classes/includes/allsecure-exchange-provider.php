<?php

final class WC_AllsecureExchange_Provider
{
    public static function paymentMethods()
    {
        /**
         * Comment/disable adapters that are not applicable
         */
        return [
            'WC_AllsecureExchange_CreditCard',
            // 'WC_AllsecureExchange_CreditCard_Amex',
            // 'WC_AllsecureExchange_CreditCard_Diners',
            // 'WC_AllsecureExchange_CreditCard_Discover',
            // 'WC_AllsecureExchange_CreditCard_Jcb',
            // 'WC_AllsecureExchange_CreditCard_Maestro',
            // 'WC_AllsecureExchange_CreditCard_Mastercard',
            // 'WC_AllsecureExchange_CreditCard_UnionPay',
            // 'WC_AllsecureExchange_CreditCard_Visa',
        ];
    }

    public static function autoloadClient()
    {
        require_once ALLSECURE_EXCHANGE_EXTENSION_BASEDIR . 'classes/vendor/autoload.php';
    }
}
