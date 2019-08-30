<?php
/**
 * Plugin Name: WooCommerce AllSecure Exchange Extension
 * Description: AllSecure Exchange for WooCommerce
 * Version: 1.1.0
 */
if (!defined('ABSPATH')) {
    exit;
}

define('ALL_SECURE_EXCHANGE_EXTENSION_URL', 'https://asxgw.com/');
define('ALL_SECURE_EXCHANGE_EXTENSION_NAME', 'AllSecure Exchange');
define('ALL_SECURE_EXCHANGE_EXTENSION_VERSION', '1.1.0');
define('ALL_SECURE_EXCHANGE_EXTENSION_UID_PREFIX', 'all_secure_exchange_');
define('ALL_SECURE_EXCHANGE_EXTENSION_BASEDIR', plugin_dir_path(__FILE__));

add_action('plugins_loaded', function () {
    require_once ALL_SECURE_EXCHANGE_EXTENSION_BASEDIR . 'classes/includes/all-secure-exchange-provider.php';
    require_once ALL_SECURE_EXCHANGE_EXTENSION_BASEDIR . 'classes/includes/all-secure-exchange-creditcard.php';
    require_once ALL_SECURE_EXCHANGE_EXTENSION_BASEDIR . 'classes/includes/all-secure-exchange-creditcard-amex.php';
    require_once ALL_SECURE_EXCHANGE_EXTENSION_BASEDIR . 'classes/includes/all-secure-exchange-creditcard-diners.php';
    require_once ALL_SECURE_EXCHANGE_EXTENSION_BASEDIR . 'classes/includes/all-secure-exchange-creditcard-discover.php';
    require_once ALL_SECURE_EXCHANGE_EXTENSION_BASEDIR . 'classes/includes/all-secure-exchange-creditcard-jcb.php';
    require_once ALL_SECURE_EXCHANGE_EXTENSION_BASEDIR . 'classes/includes/all-secure-exchange-creditcard-maestro.php';
    require_once ALL_SECURE_EXCHANGE_EXTENSION_BASEDIR . 'classes/includes/all-secure-exchange-creditcard-mastercard.php';
    require_once ALL_SECURE_EXCHANGE_EXTENSION_BASEDIR . 'classes/includes/all-secure-exchange-creditcard-unionpay.php';
    require_once ALL_SECURE_EXCHANGE_EXTENSION_BASEDIR . 'classes/includes/all-secure-exchange-creditcard-visa.php';

    add_filter('woocommerce_payment_gateways', function ($methods) {
        foreach (WC_AllSecureExchange_Provider::paymentMethods() as $paymentMethod) {
            $methods[] = $paymentMethod;
        }
        return $methods;
    }, 0);
});
