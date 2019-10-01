<?php
/**
 * Plugin Name: WooCommerce AllSecure Exchange Extension
 * Description: AllSecure Exchange for WooCommerce
 * Version: 1.2.0
 */
if (!defined('ABSPATH')) {
    exit;
}

define('ALLSECURE_EXCHANGE_EXTENSION_URL', 'https://asxgw.com/');
define('ALLSECURE_EXCHANGE_EXTENSION_NAME', 'AllSecure Exchange');
define('ALLSECURE_EXCHANGE_EXTENSION_VERSION', '1.2.0');
define('ALLSECURE_EXCHANGE_EXTENSION_UID_PREFIX', 'allsecure_exchange_');
define('ALLSECURE_EXCHANGE_EXTENSION_BASEDIR', plugin_dir_path(__FILE__));

add_action('plugins_loaded', function () {
    require_once ALLSECURE_EXCHANGE_EXTENSION_BASEDIR . 'classes/includes/allsecure-exchange-provider.php';
    require_once ALLSECURE_EXCHANGE_EXTENSION_BASEDIR . 'classes/includes/allsecure-exchange-creditcard.php';
    require_once ALLSECURE_EXCHANGE_EXTENSION_BASEDIR . 'classes/includes/allsecure-exchange-creditcard-amex.php';
    require_once ALLSECURE_EXCHANGE_EXTENSION_BASEDIR . 'classes/includes/allsecure-exchange-creditcard-diners.php';
    require_once ALLSECURE_EXCHANGE_EXTENSION_BASEDIR . 'classes/includes/allsecure-exchange-creditcard-discover.php';
    require_once ALLSECURE_EXCHANGE_EXTENSION_BASEDIR . 'classes/includes/allsecure-exchange-creditcard-jcb.php';
    require_once ALLSECURE_EXCHANGE_EXTENSION_BASEDIR . 'classes/includes/allsecure-exchange-creditcard-maestro.php';
    require_once ALLSECURE_EXCHANGE_EXTENSION_BASEDIR . 'classes/includes/allsecure-exchange-creditcard-mastercard.php';
    require_once ALLSECURE_EXCHANGE_EXTENSION_BASEDIR . 'classes/includes/allsecure-exchange-creditcard-unionpay.php';
    require_once ALLSECURE_EXCHANGE_EXTENSION_BASEDIR . 'classes/includes/allsecure-exchange-creditcard-visa.php';

    add_filter('woocommerce_payment_gateways', function ($methods) {
        foreach (WC_AllsecureExchange_Provider::paymentMethods() as $paymentMethod) {
            $methods[] = $paymentMethod;
        }
        return $methods;
    }, 0);
});
