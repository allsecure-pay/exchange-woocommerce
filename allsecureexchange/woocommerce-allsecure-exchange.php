<?php
/**
 * Plugin Name: WooCommerce AllSecure Exchange Extension
 * Description: AllSecure Exchange for WooCommerce
 * Version: 1.7.7
 * Author: AllSecure Exchange
 * WC requires at least: 3.6.0
 * WC tested up to: 4.0.0
 */
if (!defined('ABSPATH')) {
    exit;
}

define('ALLSECURE_EXCHANGE_EXTENSION_URL', 'https://asxgw.com/');
define('ALLSECURE_EXCHANGE_EXTENSION_TEST_URL', 'https://asxgw.paymentsandbox.cloud/');
define('ALLSECURE_EXCHANGE_EXTENSION_NAME', 'AllSecure Exchange');
define('ALLSECURE_EXCHANGE_EXTENSION_VERSION', '1.7.7');
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

    // add_filter('woocommerce_before_checkout_form', function(){
    add_filter('the_content', function($content){
        if(is_checkout_pay_page()) {
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
	
	/* Add the gateway footer to WooCommerce */
	function allsecureexchange_footer() {
		$selected_allsecure = new WC_AllsecureExchange_CreditCard;
		$selectedBanner = $selected_allsecure->get_selected_banner();
		$selectedCards = $selected_allsecure->get_selected_cards();
		$selectedBank = $selected_allsecure->get_merchant_bank();
		if (strpos($selectedCards, 'VISA') !== false) { $visa =  '<img src="' . plugin_dir_url( __FILE__ ) . 'assets/images/'.$selectedBanner.'/visa.svg">';} else $visa = '';
		if (strpos($selectedCards, 'MASTERCARD') !== false) { $mastercard = '<img src="' . plugin_dir_url( __FILE__ ) . 'assets/images/'.$selectedBanner.'/mastercard.svg">';} else $master = '';
		if (strpos($selectedCards, 'MAESTRO') !== false) { $maestro = '<img src="' . plugin_dir_url( __FILE__ ) . 'assets/images/'.$selectedBanner.'/maestro.svg">';} else $maestro = '';
		if (strpos($selectedCards, 'AMEX') !== false) {$amex = '<img src="' . plugin_dir_url( __FILE__ ) . 'assets/images/'.$selectedBanner.'/amex.svg">';} else $amex = '';
		if (strpos($selectedCards, 'DINERS') !== false) {$diners = '<img src="' . plugin_dir_url( __FILE__ ) . 'assets/images/'.$selectedBanner.'/diners.svg">';} else $diners = '';
		if (strpos($selectedCards, 'JCB') !== false) {$jcb = '<img src="' . plugin_dir_url( __FILE__ ) . 'assets/images/'.$selectedBanner.'/jcb.svg">';} else $jcb = '';
		$allsecure  = '<a href="https://www.allsecure.rs" target="_new"><img src="' . plugin_dir_url( __FILE__ ) . 'assets/images/'.$selectedBanner.'/allsecure.svg"></a>';
		if ($selectedBank == 'hbm') {
			$bankUrl = 'https://www.hipotekarnabanka.com/'; 
		} else if ($selectedBank == 'aik') {
			$bankUrl = 'https://www.aikbanka.rs/'; 
		} else {
			$bankUrl = '#';
		}
		$bank = '<a href="'.$bankUrl.'" target="_new" ><img src="' . plugin_dir_url( __FILE__ ) . 'assets/images/'.$selectedBanner.'/'.$selectedBank.'.svg"></a>';
		$vbv = '<a href="https://rs.visa.com/pay-with-visa/security-and-assistance/protected-everywhere.html" target="_new" ><img src="' . plugin_dir_url( __FILE__ ) . 'assets/images/'.$selectedBanner.'/visa_secure.svg"></a>';
		$mcsc = '<a href="https://www.mastercard.rs/sr-rs/consumers/identity-check.html" target="_new" ><img src="' . plugin_dir_url( __FILE__ ) . 'assets/images/'.$selectedBanner.'/mc_idcheck.svg"></a>';
		$allsecure_cards = $visa.''.$mastercard.''.$maestro.''.$diners.''.$amex.''.$jcb ;
		if ($selectedBanner !== 'none') {
			$allsecure_banner = '<div id="allsecure_exchange_banner"><div class="allsecure">'.$allsecure.'</div><div class="allsecure_threeds">'.$vbv.' '.$mcsc.'</div><div class="allsecure_cards">'.$allsecure_cards.'</div><div class="allsecure_bank">'.$bank.'</div></div>';
			wp_enqueue_style( 'allsecure_style', plugin_dir_url( __FILE__ ) . 'assets/css/allsecure-exchange-style.css', array(), null );
			echo  $allsecure_banner;
		}
	}
	add_filter('wp_footer', 'allsecureexchange_footer'); 

});
