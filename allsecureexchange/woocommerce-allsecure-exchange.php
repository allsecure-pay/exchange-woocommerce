<?php
/**
 * Plugin Name: WooCommerce AllSecure Exchange Extension
 * Plugin URI: https://help.allsecure.xyz
 * Description: AllSecure Exchange for WooCommerce
 * Version: 1.9.9
 * Tested up to: 5.8.4
 * Author: AllSecure Exchange Team
 * WC requires at least: 3.6.0
 * WC tested up to: 6.1.2
 */

 
if (!defined('ABSPATH')) {
    exit;
}

define('ALLSECURE_EXCHANGE_EXTENSION_URL', 'https://asxgw.com/');
define('ALLSECURE_EXCHANGE_EXTENSION_TEST_URL', 'https://asxgw.paymentsandbox.cloud/');
define('ALLSECURE_EXCHANGE_EXTENSION_NAME', 'AllSecure Exchange');
define('ALLSECURE_EXCHANGE_EXTENSION_VERSION', '1.9.9');
define('ALLSECURE_EXCHANGE_EXTENSION_UID_PREFIX', 'allsecure_exchange_');
define('ALLSECURE_EXCHANGE_EXTENSION_BASEDIR', plugin_dir_path(__FILE__));

add_action('plugins_loaded', function () {
    require_once ALLSECURE_EXCHANGE_EXTENSION_BASEDIR . 'classes/includes/allsecure-exchange-provider.php';
	require_once ALLSECURE_EXCHANGE_EXTENSION_BASEDIR . 'classes/includes/allsecure-exchange-compliance.php';
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

    // add_filter('woocommerce_before_checkout_form', function(){
    add_filter('the_content', function($content){
        if(is_checkout_pay_page() || is_checkout()) {
            if(!empty($_GET['gateway_return_result']) && $_GET['gateway_return_result'] == 'error') {
                wc_print_notice(__('Payment failed or was declined', 'woocommerce'), 'error');
            }
        }
        return $content;
    }, 0, 1);

    add_action( 'init', 'woocommerce_clear_cart_url' );
    function woocommerce_clear_cart_url() {
        if (isset( $_GET['clear-cart']) && is_order_received_page()) {
            global $woocommerce;

            $woocommerce->cart->empty_cart();
        }
    }
	
	add_action( 'init', 'allsecureexchange_load_plugin_textdomain' );
	function allsecureexchange_load_plugin_textdomain() {
		load_plugin_textdomain( 'allsecureexchange', FALSE, dirname(plugin_basename(__FILE__))."/languages");
	}
});
