<?php

final class WC_AllSecureExchange_Provider
{
    public static function paymentMethods()
    {
		/**
         * Comment/disable adapters that are not applicable
         */
        return [
            'WC_AllSecureExchange_CreditCard',
            // 'WC_AllSecureExchange_CreditCard_Amex',
            // 'WC_AllSecureExchange_CreditCard_Diners',
            // 'WC_AllSecureExchange_CreditCard_Discover',
            // 'WC_AllSecureExchange_CreditCard_Jcb',
            // 'WC_AllSecureExchange_CreditCard_Maestro',
            // 'WC_AllSecureExchange_CreditCard_Mastercard',
            // 'WC_AllSecureExchange_CreditCard_UnionPay',
            // 'WC_AllSecureExchange_CreditCard_Visa',
        ];
    }

    public static function autoloadClient()
    {
        require_once ALL_SECURE_EXCHANGE_EXTENSION_BASEDIR . 'classes/vendor/autoload.php';
    }
}
