<?php
/**
 * Plugin Name: AllSecure Exchange Payment Gateway WooCommerce Extension
 * Description: AllSecure Exchange Payment Gateway for WooCommerce
 * Version: 1.0.0
 */
if (!defined( 'ABSPATH' ) ) {
    exit;
}

define('PAYMENTGATEWAY_EXTENSION_URL', 'https://asxgw.com/');
define('PAYMENTGATEWAY_EXTENSION_NAME', 'AllSecure Exchange Payment Gateway Extension');
define('PAYMENTGATEWAY_EXTENSION_VERSION', '1.0.0');
define('PAYMENTGATEWAY_EXTENSION_UID_PREFIX', 'payment_gateway_');
define('PAYMENTGATEWAY_EXTENSION_BASEDIR', plugin_dir_path( __FILE__ ));

add_action('plugins_loaded', function (){
    require_once PAYMENTGATEWAY_EXTENSION_BASEDIR . 'classes/includes/gateway-provider.php';
    require_once PAYMENTGATEWAY_EXTENSION_BASEDIR . 'classes/includes/gateway-creditcard.php';
    require_once PAYMENTGATEWAY_EXTENSION_BASEDIR . 'classes/includes/gateway-creditcard-amex.php';
    require_once PAYMENTGATEWAY_EXTENSION_BASEDIR . 'classes/includes/gateway-creditcard-diners.php';
    require_once PAYMENTGATEWAY_EXTENSION_BASEDIR . 'classes/includes/gateway-creditcard-discover.php';
    require_once PAYMENTGATEWAY_EXTENSION_BASEDIR . 'classes/includes/gateway-creditcard-jcb.php';
    require_once PAYMENTGATEWAY_EXTENSION_BASEDIR . 'classes/includes/gateway-creditcard-maestro.php';
    require_once PAYMENTGATEWAY_EXTENSION_BASEDIR . 'classes/includes/gateway-creditcard-mastercard.php';
    require_once PAYMENTGATEWAY_EXTENSION_BASEDIR . 'classes/includes/gateway-creditcard-unionpay.php';
    require_once PAYMENTGATEWAY_EXTENSION_BASEDIR . 'classes/includes/gateway-creditcard-visa.php';

    add_filter( 'woocommerce_payment_gateways', function ($methods) {
        foreach (WC_Payment_Gateway_Provider::paymentMethods() as $paymentMethod) {
            $methods[] = $paymentMethod;
        }

        return $methods;
    }, 0 );
});
