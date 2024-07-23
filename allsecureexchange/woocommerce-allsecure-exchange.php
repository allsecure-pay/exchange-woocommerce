<?php
/*
Plugin Name: AllSecure Exchange
Description: AllSecure Exchange for WooCommerce
Version: 2.0.5
Requires at least: 4.0
Tested up to: 6.5.4
WC requires at least: 2.4
WC tested up to: 8.9.3
Requires PHP: 5.5
Author: <a href="https://www.allsecure.eu/">AllSecure</a>   
Author URI: https://www.allsecure.eu/
License: MIT
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('ALLSECUREEXCHANGE_VERSION', '2.0.5');
define('ALLSECUREEXCHANGE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ALLSECUREEXCHANGE_PLUGIN_PATH', plugin_dir_path(__FILE__));

require_once ALLSECUREEXCHANGE_PLUGIN_PATH.'vendor/psr/log/initClientAutoload.php';
require_once ALLSECUREEXCHANGE_PLUGIN_PATH.'vendor/exchange-php-client/initClientAutoload.php';

use Exchange\Client\Client as AllsecureClient;
use Exchange\Client\Data\Customer as AllsecureCustomer;
use Exchange\Client\Transaction\Debit as AllsecureDebit;
use Exchange\Client\Transaction\Preauthorize as AllsecurePreauthorize;
use Exchange\Client\Transaction\Result as AllsecureResult;
use Exchange\Client\Callback\Result as AllsecureCallbackResult;
use Exchange\Client\StatusApi\StatusRequestData;
use Exchange\Client\Transaction\Capture as AllsecureCapture;
use Exchange\Client\Transaction\Refund as AllsecureRefund;
use Exchange\Client\Transaction\VoidTransaction as AllsecureVoidTransaction;

/**
* Add custom order status
*/
add_action('init', 'load_textdomain_and_add_custom_order_status');
function load_textdomain_and_add_custom_order_status() {
    load_plugin_textdomain( 'allsecureexchange', FALSE, dirname(plugin_basename(__FILE__))."/languages");
    register_post_status( 'wc-authorised', 
        array(
            'label'                     => __( 'Authorised', 'allsecureexchange'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop( 'Authorised <span class="count">(%s)</span>', 'Authorised <span class="count">(%s)</span>' )
        )
    );
    register_post_status( 'wc-allsecurepending', 
        array(
            'label'                     => __( 'Allsecure Pending', 'allsecureexchange'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop( 'Allsecure Pending <span class="count">(%s)</span>', 'Allsecure Pending <span class="count">(%s)</span>' )
        )
    );
}

/**
 * Add new order status to woocommerce
 */
add_filter('wc_order_statuses', 'add_order_statuses');
function add_order_statuses( $order_statuses ) {
    $order_statuses['wc-authorised'] = __( 'Authorised', 'allsecureexchange');
    $order_statuses['wc-allsecurepending'] = __( 'Allsecure Pending', 'allsecureexchange');
    return $order_statuses;
}

/**
 * Do not display payment option in the checkout if not entered the required credentials
 */
add_filter( 'woocommerce_available_payment_gateways', 'enable_allsecureexchange_gateway' );
function enable_allsecureexchange_gateway( $available_gateways ) {
    if ( is_admin() ) return $available_gateways;

    if ( isset( $available_gateways['allsecureexchange'] )) {
        $settings = get_option('woocommerce_allsecureexchange_settings');
        
        if (empty($settings['api_user'])) {
            unset( $available_gateways['allsecureexchange'] );
        } elseif (empty($settings['api_password'])) {
            unset( $available_gateways['allsecureexchange'] );
        } elseif (empty($settings['api_key'])) {
            unset( $available_gateways['allsecureexchange'] );
        } elseif (empty($settings['shared_secret'])) {
            unset( $available_gateways['allsecureexchange'] );
        }
        
        if ($settings['checkout_mode'] == 'paymentjs') {
            if (empty($settings['integration_key'])) {
                unset( $available_gateways['allsecureexchange'] );
            }
        }
    } 
    return $available_gateways;
}

add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

/**
 * Initiate AllSecure Exchange Payment once plugin is ready
 */
add_action('plugins_loaded', 'woocommerce_allsecureexchange_init');
function woocommerce_allsecureexchange_init() {
    if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . '/wp-admin/includes/plugin.php';
    }
    if (!is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
            return;
    }

    class WC_AllsecureExchange extends WC_Payment_Gateway {

        public $domain;

        /**
         * Constructor for the gateway.
         */
        public function __construct($need_instance=false) {
            $this->domain = 'allsecureexchange';
            
            $this->supports = array(
                'products',
                'refunds'
            );

            $this->id = 'allsecureexchange';
            $this->prefix = 'allsecureexchange_';
            $this->icon = ALLSECUREEXCHANGE_PLUGIN_URL . 'assets/images/logo.png';
            $this->method_title = __('AllSecure Exchange', $this->domain);

            // Define user set variables
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->operation_mode = $this->get_option('operation_mode');
            $this->checkout_mode = $this->get_option('checkout_mode');
            $this->api_user = $this->get_option('api_user');
            $this->api_password = $this->get_option('api_password');
            $this->api_key = $this->get_option('api_key');
            $this->shared_secret = $this->get_option('shared_secret');
            $this->integration_key = $this->get_option('integration_key');
            $this->payment_action = $this->get_option('payment_action');
            $this->card_supported = $this->get_option('card_supported');
            $this->transaction_email = $this->get_option('transaction_email');
            $this->transaction_confirmation_page = $this->get_option('transaction_confirmation_page');
            $this->success_order_status = 'processing';
            $this->debug = $this->get_option('debug');
            $this->logo_style = $this->get_option('logo_style');
            $this->has_fields = true;
            
            $this->enable_installment = get_option('woocommerce_allsecureexchange_enable_installment');

            if (!$this->option_exists("woocommerce_allsecureexchange_installment_bins")) {
                $this->installment_bins = array();
            } else {
                $this->installment_bins = get_option('woocommerce_allsecureexchange_installment_bins');
                if (!empty($this->installment_bins)) {
                    $this->installment_bins = json_decode($this->installment_bins, true);
                } else {
                    $this->installment_bins = array();
                }
            }

            if (!$this->option_exists("woocommerce_allsecureexchange_allowed_installments")) {
                $this->allowed_installments = array();
            } else {
                $this->allowed_installments = get_option('woocommerce_allsecureexchange_allowed_installments');
                if (!empty($this->allowed_installments)) {
                    $this->allowed_installments = json_decode($this->allowed_installments, true);
                } else {
                    $this->allowed_installments = array();
                }
            }
            
            if (!$need_instance) {
                // Load the settings.
                $this->init_form_fields();
                $this->init_settings();

                // Actions
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                add_action('woocommerce_api_'. strtolower("WC_AllsecureExchange"), array( $this, 'check_api_response' ) );
                add_filter('woocommerce_gateway_icon', array($this, 'custom_payment_gateway_icons'), 11, 3 );
                add_filter('woocommerce_thankyou_order_received_text', array($this, 'thankyou_page'), 10, 2);
                add_action( 'woocommerce_email_after_order_table', array($this,'email_after_order_table'), 10, 1);
                add_action('woocommerce_admin_order_totals_after_total', array($this, 'admin_order_totals'), 10, 2);
                add_action('woocommerce_order_item_add_action_buttons', array($this, 'admin_order_action_buttons'), 10, 1);
                add_action('wp_enqueue_scripts',  array($this, 'load_front_assets'));
                add_filter('script_loader_tag', array($this, 'add_paymentjs_tag'), 10, 3);
                add_action('admin_footer', array( $this, 'allsecure_admin_footer'), 10, 3 );
            }
        }
        
        /**
         * Initialize Gateway Settings Form Fields.
         */
        public function init_form_fields() {

	    $field_arr = array(
                'enabled' => array(
                    'title' => __('Active', $this->domain),
                    'type' => 'checkbox',
                    'label' => __(' ', $this->domain),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('Title', $this->domain),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', $this->domain),
                    'default' => __('Credit Card', $this->domain),
                    'desc_tip' => false,
                ),
                'description' => array(
                    'title' => __('Description', $this->domain),
                    'type' => 'textarea',
                    'description' => __('This controls the descriptive text which the user sees while choosing this payment option.', $this->domain),
                    'default' => '',
                    'desc_tip' => false,
                ),
                'operation_mode' => array(
                    'title' => __('Operation Mode', $this->domain),
                    'type' => 'select',
                    'label' => __('Operation Mode', $this->domain),
                    'options' =>  array('live' => __('Live', $this->domain), 'test' => __('Test', $this->domain)),
                    'default' => 'test',
                ),
                'checkout_mode' => array(
                    'title' => __('Checkout Mode', $this->domain),
                    'type' => 'select',
                    'label' => __('Checkout Mode', $this->domain),
                    'options' =>  array('paymentjs' => __('Payment.js Javascript Integration', $this->domain), 'redirect' => __('Full-Page Redirect', $this->domain)),
                    'default' => 'redirect',
                    'description' => __('<strong>Payment.js Javascript Integration</strong><br/>
                    With the payment.js integration you can securely accept card payments and 
                    integrate card number and CVV collection directly into your shop website 
                    without the need for redirecting to a separate payment form. 
                    The payment.js library renders 2 separate iFrames for card number and CVV/CVC in your checkout page. 
                    This reduces your PCI-DSS scope to as low as it can get (PCI-DSS SAQ-A).<br/><br/>
                    <strong>Full-Page Redirect</strong><br/>
                    Customer are redirected to the Payment Service Provider (PSP) page. 
                    Here, the customer fills in his or her payment details, and after paying, 
                    is redirected back to your website to complete the checkout process.', $this->domain),
                ),
                'api_credentials' => array(
                    'title'       => __('API Credentials', $this->domain),
                    'type'        => 'title',
                ),
                'api_user' => array(
                    'title' => __('API User', $this->domain),
                    'type' => 'text',
                    'description' => __('Please enter your Exchange API User. This is needed in order to take the payment.', $this->domain),
                    'default' => '',
                ),
                'api_password' => array(
                    'title' => __('API Password', $this->domain),
                    'type' => 'text',
                    'description' => __('Please enter your Exchange API Password. This is needed in order to take the payment.', $this->domain),
                    'default' => '',
                ),
                'api_key' => array(
                    'title' => __('API Key', $this->domain),
                    'type' => 'text',
                    'description' => __('Please enter your Exchange API Key. This is needed in order to take the payment.', $this->domain),
                    'default' => '',
                ),
                'shared_secret' => array(
                    'title' => __('Shared Secret', $this->domain),
                    'type' => 'text',
                    'description' => __('Please enter your Exchange API Shared Secret. This is needed in order to take the payment.', $this->domain),
                    'default' => '',
                ),
                'integration_key' => array(
                    'title' => __('Integration Key', $this->domain),
                    'type' => 'text',
                    'description' => __('Public Integration Key required only if payment.js integration required.', $this->domain),
                    'default' => '',
                ),
                'payment_details' => array(
                    'title' => __('Payment Details', 'allsecureexchange' ),
                    'type' => 'title',
                ),
                'payment_action' => array(
                    'title' => __('Transaction Type', $this->domain),
                    'type' => 'select',
                    'label' => __('Transaction Type', $this->domain),
                    'options' =>  array('debit' => __('Debit', $this->domain), 'preauthorize' => __('Preauthorize', $this->domain)),
                    'default' => 'debit',
                    'description' => __('<strong>Debit: </strong>Debits the end customer with the given amount.<br/>
                    <strong>Preauthorize: </strong>Reserves the payment amount on the customer\'s payment instrument. 
                    Preauthorization must be captured afterwards to conclude the transaction.', $this->domain),
                ),
                'card_supported' => array(
                    'title' => __('Accepted Cards', $this->domain),
                    'type' => 'multiselect',
                    'description' => __('Select the cards which you would like to accept', $this->domain),
                    'options' => array(
                        'VISA' => __('VISA', $this->domain),
                        'MASTERCARD' => __('MASTERCARD', $this->domain),
                        'MAESTRO' => __('MAESTRO', $this->domain),
                        'AMEX' => __('AMEX', $this->domain),
                        'DINERS' => __('DINERS', $this->domain),
                        'JCB'  => __('JCB', $this->domain),
                        'DINACARD'  => __('DINA', $this->domain),
                        'DISCOVER'  => __('DISCOVER', $this->domain),
                    ),
                    'class' => 'wc-enhanced-select',
                ),
                'transaction_email' => array(
                    'title' => __('Transaction Details', $this->domain),
                    'type' => 'checkbox',
                    'label' => __('Enable transaction details in the confirmation email', $this->domain),
                    'default' => 'no',
                    'description'=> __( 'When enabled, plugin will add transaction details in the order confirmation email.', $this->domain),
                ),
                'transaction_confirmation_page' => array(
                    'title' => __('', $this->domain),
                    'type' => 'checkbox',
                    'label' => __('Enable transaction details in the confirmation page', $this->domain),
                    'default' => 'no',
                    'description'=> __( 'When enabled, plugin will add transaction details in the order confirmation page.', $this->domain),
                ),
                'debug' => array(
                    'title' => __('Debug', $this->domain),
                    'type' => 'checkbox',
                    'label' => __(' ', $this->domain),
                    'default' => 'no'
                ),
               'design_details' => array(
                    'title' => __('Style Details', 'allsecureexchange' ),
                    'type' => 'title',
                ),
                'logo_style' => array(
                    'title' => __('Logo Style', $this->domain),
                    'type' => 'textarea',
                    'description' => __('If payment logos are not displayed properly on your site theme, please add styles here to fix that or contact the developer they may write matching styles to your theme and paste it here.', $this->domain),
                    'default' => $this->logo_style,
                    'desc_tip' => false,
                ), 
            );

            $this->form_fields = $field_arr;
        }

        public function isHPOSEnabled() {
            $status = false;
            
            if ( 
                class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) && 
                Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
            ) {
                $status = true;
            }
            
            return $status;
        }

        public function getOrder($order_id) {
            if ($this->isHPOSEnabled()) {
                $order = wc_get_order( $order_id );
            } else {
                $order = new WC_Order($order_id);
            }
            
            return $order;
        }
        
        public function getOrderMetaData($order, $order_id, $key, $single) {
            if ($this->isHPOSEnabled()) {
                return $order->get_meta( $key, $single );
            } else {
                return get_post_meta( $order_id, $key, $single );
            }
        }
        
        /**
         * Process Gateway Settings Form Fields.
         */
	public function process_admin_options() {
            $this->init_settings();

            $post_data = $this->get_post_data();
            if (empty($post_data['woocommerce_allsecureexchange_api_user'])) {
                WC_Admin_Settings::add_error(__('Please enter Allsecure Exchange API User', $this->domain));
            } elseif (empty($post_data['woocommerce_allsecureexchange_api_password'])) {
                WC_Admin_Settings::add_error(__('Please enter Allsecure Exchange API Password', $this->domain));
            } elseif (empty($post_data['woocommerce_allsecureexchange_api_key'])) {
                WC_Admin_Settings::add_error(__('Please enter Allsecure Exchange API Key', $this->domain));
            } elseif (empty($post_data['woocommerce_allsecureexchange_shared_secret'])) {
                WC_Admin_Settings::add_error(__('Please enter allsecureexchange API Shared Secret', $this->domain));
            } else {
                $noerror = true;
                if ($post_data['woocommerce_allsecureexchange_checkout_mode'] == 'paymentjs') {
                    if (empty($post_data['woocommerce_allsecureexchange_integration_key'])) {
                        WC_Admin_Settings::add_error(__('Please enter Allsecure Exchange Public Integration Key since Payment.js checkout option is enabled.', $this->domain));
                        $noerror = false;
                    } elseif (empty($post_data['woocommerce_allsecureexchange_card_supported'])) {
                        WC_Admin_Settings::add_error(__('Please select atleast one card type since Payment.js checkout option is enabled.', $this->domain));
                        $noerror = false;
                    } 
                }
                if ($noerror) {
                    foreach ( $this->get_form_fields() as $key => $field ) {
                        $setting_value = $this->get_field_value( $key, $field, $post_data );
                        $this->settings[ $key ] = $setting_value;
                    }
                    
                    if (isset($post_data['woocommerce_allsecureexchange_enable_installment'])) {
                        update_option('woocommerce_allsecureexchange_enable_installment', 1);
                    } else {
                        update_option('woocommerce_allsecureexchange_enable_installment', 0);
                    }
                    $this->enable_installment = get_option('woocommerce_allsecureexchange_enable_installment');

                    if ($this->enable_installment) {
                        if (isset($post_data['woocommerce_allsecureexchange_installment_bins'])) {
                            $installment_bins = $post_data['woocommerce_allsecureexchange_installment_bins'];
                            $allowed_installments = $post_data['woocommerce_allsecureexchange_allowed_installments'];

                            $installment_bins_sanitized = array();
                            $cashier_emails_sanitized = array();
                            foreach ($installment_bins as $key => $val) {
                                if (!empty($val)) {
                                    $installment_bins_sanitized[] = sanitize_text_field($val);
                                    $allowed_installments_sanitized[] = sanitize_text_field($allowed_installments[$key]);
                                }
                            }
                            $this->installment_bins = $installment_bins_sanitized;
                            $this->allowed_installments = $allowed_installments_sanitized;
                            if (count($installment_bins_sanitized) > 0) {
                                update_option('woocommerce_allsecureexchange_installment_bins', json_encode($installment_bins_sanitized));
                                update_option('woocommerce_allsecureexchange_allowed_installments', json_encode($allowed_installments_sanitized));
                            } else {
                                delete_option('woocommerce_allsecureexchange_installment_bins');
                                delete_option('woocommerce_allsecureexchange_allowed_installments');
                            }
                         } else {
                            delete_option('woocommerce_allsecureexchange_installment_bins');
                            delete_option('woocommerce_allsecureexchange_allowed_installments');
                            $this->installment_bins = array();
                            $this->allowed_installments = array();
                        }
                    } else {
                        delete_option('woocommerce_allsecureexchange_installment_bins');
                        delete_option('woocommerce_allsecureexchange_allowed_installments');
                        $this->installment_bins = array();
                        $this->allowed_installments = array();
                    }

                    return update_option( $this->get_option_key(), apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings ) );
                }
             }
	}
        
        public function allsecure_admin_footer() {
            if (isset($_GET['page']) && sanitize_text_field($_GET['page']) == 'wc-settings' && isset($_GET['section']) && sanitize_text_field($_GET['section']) == 'allsecureexchange') { 
                $allsecuretab = 'settings';
                if(isset($_GET['allsecuretab'])) {
                    $allsecuretab = sanitize_text_field($_GET['allsecuretab']);
                }
            ?>
            <nav id="allsecure-tabs" class="nav-tab-wrapper" style="display: none">
                <a id="allsecure-setting-tab" href="?page=wc-settings&section=allsecureexchange&tab=checkout&allsecuretab=settings" class="nav-tab <?php echo (($allsecuretab == 'settings') ? 'nav-tab-active':'')?>"><?php echo __('Settings', $this->domain)?></a>
                <a id="allsecure-installment-settings-tab" href="?page=wc-settings&section=allsecureexchange&allsecuretab=installment-settings&tab=checkout" class="nav-tab <?php echo (($allsecuretab == 'installment-settings') ? 'nav-tab-active':'')?>"><?php echo __('Installment Settings', $this->domain)?></a>
            </nav>
            <div class="tab-content" id="allsecure-tab-content-installment-settings" style="display: none">
                <table class="form-table">
                    <tbody>
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <label for="woocommerce_allsecureexchange_enable_installment"><?php echo __('Enable Installment Payments', $this->domain)?></label>
                            </th>
                            <td class="forminp">
                                <fieldset>
                                    <legend class="screen-reader-text"><span><?php echo __('Enable Installment Payments', $this->domain)?></span></legend>
                                    <label for="woocommerce_allsecureexchange_enable_installment">
                                        <input class="" type="checkbox" name="woocommerce_allsecureexchange_enable_installment" id="woocommerce_allsecureexchange_enable_installment" style="" value="1" 
                                            <?php echo $this->enable_installment ? 'checked="checked"':''?> >
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                        <tr valign="top">
                            <td class="forminp" colspan="2" id="bin_settings">
                                <div><?php echo __('Enter Installation Eligible BIN Information:', $this->domain)?></div>
                                <div class="field_wrapper">
                                    <?php 
                                    if (count($this->installment_bins) > 0) {
                                        $i = 0;
                                        foreach($this->installment_bins as $key => $val) {
                                            $i++;
                                    ?>
                                    <table style="border-bottom: 1px solid #ccc; margin-bottom: 10px" <?php if ($i > 1) {?>class="dynamic-field-table"<?php } ?>>
                                        <tr valign="top">
                                            <th scope="row" class="titledesc">
                                                <label><?php echo __('BIN', $this->domain)?></label>
                                            </th>
                                            <td class="forminp">
                                                <input type="number" name="woocommerce_allsecureexchange_installment_bins[]" value="<?php echo $val?>"/>
                                            </td>
                                            <td>
                                                <?php if ($i > 1) {?>
                                                <a href="javascript:void(0);" class="btn button-secondary remove_button" title="Remove field"><?php echo __('Remove', $this->domain)?></a>
                                                <?php } else { ?>
                                                &nbsp;
                                                <?php } ?>
                                            </td>
                                        </tr>
                                        <tr valign="top">
                                            <th scope="row" class="titledesc">
                                                <label><?php echo __('Allowed Installments', $this->domain)?></label>
                                            </th>
                                            <td class="forminp">
                                                <input name="woocommerce_allsecureexchange_allowed_installments[]" value="<?php echo (isset($this->allowed_installments[$key]) ? $this->allowed_installments[$key] : '') ?>" />
                                                <p><?php echo __('Enter comma separated eg: 3,6,9,12', $this->domain)?></p>
                                            </td>
                                            <td>&nbsp;</td>
                                        </tr>
                                    </table>
                                    <?php 
                                        }
                                    } else {
                                    ?>
                                    <table style="border-bottom: 1px solid #ccc; margin-bottom: 10px">
                                        <tr valign="top">
                                            <th scope="row" class="titledesc">
                                                <label><?php echo __('BIN', $this->domain)?></label>
                                            </th>
                                            <td class="forminp">
                                                <input type="number" name="woocommerce_allsecureexchange_installment_bins[]" value=""/>
                                            </td>
                                            <td>
                                                  &nbsp;
                                             </td>
                                        </tr>
                                        <tr valign="top">
                                            <th scope="row" class="titledesc">
                                                <label><?php echo __('Allowed Installments', $this->domain)?></label>
                                            </th>
                                            <td class="forminp">
                                                <input type="text" name="woocommerce_allsecureexchange_allowed_installments[]" value=""/>
                                                <p><?php echo __('Enter comma separated eg: 3,6,9,12', $this->domain)?></p>
                                            </td>
                                            <td>&nbsp;</td>
                                        </tr>
                                    </table>
                                    <?php
                                    }
                                    ?>
                                </div>
                                <div>
                                    <a href="javascript:void(0);" class="btn button-secondary allsecure_add_button" title="Add field"><?php echo __('Add New', $this->domain)?></a>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <script type="text/javascript">
                var allsecuretab = '<?php echo $allsecuretab?>';
                jQuery(document).ready(function(){
                    var maxField = 100;
                    var addButton = jQuery('.allsecure_add_button');
                    var wrapper = jQuery('.field_wrapper');
                    var fieldHTML = '<table class="dynamic-field-table" style="border-bottom: 1px solid #ccc; margin-bottom: 10px"><tr valign="top"><th scope="row" class="titledesc"><label><?php echo __('BIN', $this->domain)?></label></th><td class="forminp"><input type="number" name="woocommerce_allsecureexchange_installment_bins[]" value=""/></td><td><a href="javascript:void(0);" class="btn button-secondary remove_button" title="Remove field"><?php echo __('Remove', $this->domain)?></a></td></tr><tr valign="top"><th scope="row" class="titledesc"><label><?php echo __('Allowed Installments', $this->domain)?></label></th><td class="forminp"><input type="text" name="woocommerce_allsecureexchange_allowed_installments[]" value=""/><p><?php echo __('Enter comma separated eg: 3,6,9,12', $this->domain)?></p></td></tr></table>';
                    var x = parseInt('<?php echo (count($this->installment_bins) == 0) ? 1 : count($this->installment_bins)?>');

                    jQuery('.wc-admin-breadcrumb').parent().after(jQuery('#allsecure-tabs'));
                    jQuery('#allsecure-tabs').after(jQuery('#allsecure-tab-content-installment-settings'));
                    jQuery('#allsecure-tabs').show();
                    if (allsecuretab == 'settings') {
                        jQuery('#woocommerce_allsecureexchange_enabled').closest('.form-table').show();
                        jQuery('#woocommerce_allsecureexchange_api_user').closest('.form-table').show();
                        jQuery('#woocommerce_allsecureexchange_payment_action').closest('.form-table').show();
                        jQuery('#woocommerce_allsecureexchange_logo_style').closest('.form-table').show();
                        
                        jQuery('#woocommerce_allsecureexchange_api_credentials').show();
                        jQuery('#woocommerce_allsecureexchange_payment_details').show();
                        jQuery('#woocommerce_allsecureexchange_design_details').show();
                         
                        jQuery('#allsecure-tab-content-installment-settings').hide();
                    } else if (allsecuretab == 'installment-settings') {
                        jQuery('#woocommerce_allsecureexchange_enabled').closest('.form-table').hide();
                        jQuery('#woocommerce_allsecureexchange_api_user').closest('.form-table').hide();
                        jQuery('#woocommerce_allsecureexchange_payment_action').closest('.form-table').hide();
                        jQuery('#woocommerce_allsecureexchange_logo_style').closest('.form-table').hide();
                        
                        jQuery('#woocommerce_allsecureexchange_api_credentials').hide();
                        jQuery('#woocommerce_allsecureexchange_payment_details').hide();
                        jQuery('#woocommerce_allsecureexchange_design_details').hide();
                        
                        jQuery('#allsecure-tab-content-installment-settings').show();
                    } 

                    if (jQuery('#woocommerce_allsecureexchange_enable_installment').is(':checked')) {
                        jQuery('#bin_settings').show();
                    } else {
                        jQuery('#bin_settings').hide();
                    }

                    jQuery('#woocommerce_allsecureexchange_enable_installment').click(function(){
                        if (jQuery('#woocommerce_allsecureexchange_enable_installment').is(':checked')) {
                            jQuery('#bin_settings').show();
                        } else {
                            jQuery('#bin_settings').hide();
                        }
                    });

                    jQuery(addButton).click(function(){
                        if(x < maxField){ 
                            x++;
                            jQuery(wrapper).append(fieldHTML);
                        } else {
                            alert('Allowed to add maximum: '+maxField);
                        }
                    });

                    jQuery(wrapper).on('click', '.remove_button', function(e){
                        e.preventDefault();
                        jQuery(this).parents('.dynamic-field-table').remove();
                        x--; 
                    });
                });
            </script>  
            <?php
            }
        }

        public function option_exists($name, $site_wide=false)
        {
            global $wpdb; 
            return $wpdb->query("SELECT * FROM ". ($site_wide ? $wpdb->base_prefix : $wpdb->prefix). "options WHERE option_name ='$name' LIMIT 1");
        }
        
        /** 
	 * Custom Credit Card Icons on a checkout page 
	**/
        public function custom_payment_gateway_icons( $icon, $gateway_id ) {
            global $wp;

            $customiseIcon = true;
            if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'wc_pos_payload') {
                $customiseIcon = false;
            }
            
            if($customiseIcon && $gateway_id == 'allsecureexchange') {
                $icon = '';
                ?><?php
                if ($this->card_supported) {
                ?>
                    
                <?php      
                    $pngs = array(
                    );
                    foreach ($this->card_supported as $card) {
                        $card = strtolower($card);
                        $extn = 'svg';
                        if (in_array($card, $pngs)) {
                            $extn = 'png';
                        }
                        ?>
                        <img src="<?php echo ALLSECUREEXCHANGE_PLUGIN_URL. '/assets/images/'.$card.'.'.$extn; ?>" alt="<?php echo esc_attr( $card )?>" style="<?php echo $this->logo_style?>">
                        <?php
                    }
                ?>
                    
                <?php        
                }
                ?><?php
            }
            
            return $icon;
        }
        
        /**
         * Process the payment and return the result.
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment($order_id) {
            global $woocommerce;

			$order = $this->getOrder($order_id);

            try {
                $transaction_token = '';
                $installment_number = '';

                $checkoutType = $this->checkout_mode;
                $action = $this->payment_action;

                if ($checkoutType == 'paymentjs') {
                    if (isset($_POST['allsecurepay_transaction_token'])) {
                        $transaction_token = $_POST['allsecurepay_transaction_token'];
                        if (!empty($transaction_token)) {
                            $transaction_token  = sanitize_text_field($transaction_token);
                        }
                    }

                    if (empty($transaction_token)) {
                        throw new \Exception(__('Invalid transaction token', $this->domain));
                    }
                    
                    if (isset($_POST['allsecurepay_pay_installment'])) {
                        $installment_number = $_POST['allsecurepay_installment_number'];
                        if (!empty($installment_number)) {
                            $installment_number  = sanitize_text_field($installment_number);
                            $action = 'debit';
                        }
                    }
                }
                
                if ($action == 'debit') {
                    $result = $this->debitTransaction($order, $transaction_token, $installment_number);
                } else {
                    $result = $this->preauthorizeTransaction($order, $transaction_token);
                }
                
                // handle the result
                if ($result->isSuccess()) {
                    $gatewayReferenceId = $result->getUuid();

                    $order->add_meta_data($this->prefix.'transaction_id', $gatewayReferenceId, true);
                    $order->add_meta_data($this->prefix.'payment_request_uuid', $gatewayReferenceId, true);
                    $order->add_meta_data($this->prefix.'transaction_mode', $this->operation_mode, true);
                    $order->add_meta_data($this->prefix.'checkout_type', $checkoutType, true);
                    $order->add_meta_data($this->prefix.'transaction_type', $action, true);
                    $order->save_meta_data();

                    // handle result based on it's returnType
                    if ($result->getReturnType() == AllsecureResult::RETURN_TYPE_ERROR) {
                        //error handling
                        $order->add_meta_data('status', 'error', true);
                        $error = $result->getFirstError();
                        $errorCode = $error->getCode();
                        if (empty($errorCode)) {
                            $errorCode = $error->getAdapterCode();
                        }
                        $errorMessage = $this->getErrorMessageByCode($errorCode);
                        throw new \Exception($errorMessage);
                    } elseif ($result->getReturnType() == AllsecureResult::RETURN_TYPE_REDIRECT) {
                        //redirect the user
                        if (!empty($installment_number)) {
                            $order->add_meta_data($this->prefix.'installment_number', $installment_number, true);
                        }
                        $order->delete_meta_data($this->prefix.'status');
                        $order->add_meta_data($this->prefix.'status', 'redirected', true);
                        $order->save_meta_data();
                        
                        $redirectLink = $result->getRedirectUrl();
                        return [
                            'result' => 'success',
                            'redirect' => $redirectLink,
			];
                    } elseif ($result->getReturnType() == AllsecureResult::RETURN_TYPE_PENDING) {
                        //payment is pending, wait for callback to complete
                        if (!empty($installment_number)) {
                            $order->add_meta_data($this->prefix.'installment_number', $installment_number, true);
                        }
                        $order->delete_meta_data($this->prefix.'status');
                        $order->add_meta_data($this->prefix.'status', 'pending', true);
                        $order->save_meta_data();
                        if ($action == 'debit') {
                            $comment1 = __('Allsecure Exchange payment request is created successfully and but payment debt status received as pending.', $this->domain);
                        } else {
                            $comment1 = __('Allsecure Exchange payment request is created successfully and but payment preauthorize status received as pending.', $this->domain);
                        }

                        $comment2 = __('Transaction ID', $this->domain).': ' .$gatewayReferenceId;
                        $comment = $comment1.$comment2;
                        
                        $order->update_status('pending', $comment);
                        $woocommerce->cart->empty_cart();
                        
                        return [
                            'result' => 'success',
                            'redirect' => add_query_arg( 'order_id', $order_id, $this->get_return_url( $order ))
			];
                    } elseif ($result->getReturnType() == AllsecureResult::RETURN_TYPE_FINISHED) {
                        //payment is finished, update your cart/payment transaction
                        if ($action == 'debit') {
                            if (!empty($installment_number)) {
                                $order->add_meta_data($this->prefix.'installment_number', $installment_number, true);
                            }
                            $order->delete_meta_data($this->prefix.'status');
                            $order->add_meta_data($this->prefix.'status', 'debited', true);
                            $order->save_meta_data();
                            $comment1 = __('Allsecure Exchange payment is successfully debited. ', $this->domain);
                            $comment2 = __('Transaction ID', $this->domain).': ' .$gatewayReferenceId;
                            $comment = $comment1.$comment2;
                            
                            $order_status = $this->getOrderStatusBySlug($this->success_order_status);
                            
                            $order->update_status($order_status, $comment);
                            $woocommerce->cart->empty_cart();
                        
                            return [
                                'result' => 'success',
                                'redirect' => add_query_arg( 'order_id', $order_id, $this->get_return_url( $order ))
                            ];
                        } else {
                            $order->delete_meta_data($this->prefix.'status');
                            $order->add_meta_data($this->prefix.'status', 'preauthorized', true);
                            $order->save_meta_data();
                            $order->payment_complete();
                            $comment1 = __('Allsecure payment is successfully reserved for manual capture. ', $this->domain);
                            $comment2 = __('Transaction ID', $this->domain).': ' .$gatewayReferenceId;
                            $comment = $comment1.$comment2;
                            
                            $order_status = 'wc-authorised';
                            $order->update_status($order_status, $comment);
                            $woocommerce->cart->empty_cart();
                        
                            return [
                                'result' => 'success',
                                'redirect' => add_query_arg( 'order_id', $order_id, $this->get_return_url( $order ))
                            ];
                        }
                    }
                } else {
                    // handle error
                    $error = $result->getFirstError();
                    $errorCode = $error->getCode();
                    if (empty($errorCode)) {
                        $errorCode = $error->getAdapterCode();
                    }
                    $errorMessage = $this->getErrorMessageByCode($errorCode);
                    throw new \Exception($errorMessage);
                }
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
                $this->log('Payment Create Catch: '.$errorMessage);
                $message = __('Payment is failed. ', $this->domain).' '.$errorMessage;

                WC()->session->set('refresh_totals', true);
                wc_add_notice($message, $notice_type = 'error');
                return array(
                    'result' => 'failure',
                    'redirect' => wc_get_checkout_url()
                );
            }
        }
        
        /**
         * Payment Gateway Handler
         *
         * @return void
         */
        public function check_api_response() {
            global $woocommerce;
            if (isset($_GET['action'])) {
                $action = sanitize_text_field($_GET['action']);
                if ($action == 'return') {
                    $this->payment_return_handler();
                } elseif ($action == 'error') {
                    $this->payment_error_handler();
                } elseif ($action == 'cancel') {
                    $this->payment_cancel_handler();
                } elseif ($action == 'webhook') {
                    $this->payment_webhook_handler();
                } elseif ($action == 'capture') {
                    $this->process_capture();
                } elseif ($action == 'void') {
                    $this->process_void();
                }
            }
            exit;
        }
        
        /**
         * Payment Gateway Return URL Handler
         *
         * @return void
         */
        public function payment_return_handler() {
            global $woocommerce;
            try {
                $this->log('Return URL Called');
                $order_id = false;
                if (isset($_GET['order_id'])) {
                    $order_id = (int)sanitize_text_field($_GET['order_id']);
                }

                if ($order_id) {
                    $order = new WC_Order($order_id);
                    $woocommerce->cart->empty_cart();
                    wp_redirect(add_query_arg( 'order_id', $order_id, $this->get_return_url( $order )));exit;
                } else {
                    throw new \Exception(__('No order data received', $this->domain));
                }
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
                $this->log('Return URL Catch: '.$errorMessage);
                $message = __('Payment is failed. ', $this->domain).' '.$errorMessage;
                wp_redirect( add_query_arg( 'egassemrorre', base64_encode($message), wc_get_checkout_url() ) );exit;
            }
        }
        
        /**
         * Payment Gateway Error URL Handler
         *
         * @return void
         */
        public function payment_error_handler() {
            try {
                $this->log('Error URL Called');
                $order_id = false;
                if (isset($_GET['order_id'])) {
                    $order_id = (int)sanitize_text_field($_GET['order_id']);
                }

                if ($order_id) {
                    $order = new WC_Order($order_id);
                    $uuid = $order->get_meta($this->prefix.'payment_request_uuid');

                    $client = $this->getClient();

                    $statusRequestData = new StatusRequestData();
                    $statusRequestData->setUuid($uuid);
                    $statusResult = $client->sendStatusRequest($statusRequestData);

                    $params = array();
                    if ($statusResult->hasErrors()) {
                        $errors = $statusResult->getErrors();
                        $error = $statusResult->getFirstError();

                        $errorCode = $error->getCode();
                        if (empty($errorCode)) {
                            $errorCode = $error->getAdapterCode();
                        }
                        $errorMessage = $this->getErrorMessageByCode($errorCode);

                        throw new \Exception($errorMessage);
                    } else {
                        throw new \Exception(__('Error from gateway.', $this->domain));
                    }
                } else {
                    throw new \Exception(__('No order data received', $this->domain));
                }
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
                $this->log('Error URL Catch: '.$errorMessage);
                $message = __('Payment is failed. ', $this->domain).' '.$errorMessage;
                wp_redirect( add_query_arg( 'egassemrorre', base64_encode($message), wc_get_checkout_url() ) );exit;
            }
        }
        
        /**
         * Payment Gateway Cancel URL Handler
         *
         * @return void
         */
        public function payment_cancel_handler() {
            try {
                $this->log('Cancel URL Called');
                $order_id = false;
                if (isset($_GET['order_id'])) {
                    $order_id = (int)sanitize_text_field($_GET['order_id']);
                }

                if ($order_id) {
                    throw new \Exception(__('Your order is cancelled.', $this->domain));
                } else {
                    throw new \Exception(__('No order data received', $this->domain));
                }
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
                $this->log('Cancel URL Catch: '.$errorMessage);
                $message = __('Payment is failed. ', $this->domain).' '.$errorMessage;
                wp_redirect( add_query_arg( 'egassemrorre', base64_encode($message), wc_get_checkout_url() ) );exit;
            }
        }
        
        /**
         * Payment Gateway Webhook URL Handler
         *
         * @return void
         */
        public function payment_webhook_handler() {
            global $woocommerce;
            
            try {
                $this->log('Webhook URL Called');
                $client = $this->getClient();

                if (!$client->validateCallbackWithGlobals()) {
                    throw new \Exception(__('Callback validation failed.', $this->domain));
                }

                $callbackResult = $client->readCallback(file_get_contents('php://input'));
                $this->log((array)($callbackResult));
                $order_id = (int)sanitize_text_field($_GET['order_id']);
                if ($order_id > 0) {
                    $order = new WC_Order($order_id);
                    if ($order && $order->get_id() > 0) {
                        $merchantTransactionId = $callbackResult->getMerchantTransactionId();
                        $decodedOrderId = $this->decodeOrderId($merchantTransactionId);
                        $this->log('Concerning order:'.$order_id);

                        if ($order_id != $decodedOrderId) {
                           throw new \Exception(__('Merchant transaction id validation failed.', $this->domain));
                        }

                        // handle result based on it's returnType
                        if ($callbackResult->getResult() == AllsecureCallbackResult::RESULT_OK) {
                            //result success
                            $gatewayReferenceId = $callbackResult->getUuid();
                            if ($callbackResult->getTransactionType() == AllsecureCallbackResult::TYPE_DEBIT) {
                                //result debit
                                if ( isset($callbackResult->getExtraData()['authCode']) ) {
                                    $order->add_meta_data($this->prefix.'AuthCode', $callbackResult->getExtraData()['authCode'], true);
                                } elseif (isset($callbackResult->getExtraData()['adapterReferenceId']) ) {
                                    $order->add_meta_data($this->prefix.'AuthCode', $callbackResult->getExtraData()['adapterReferenceId'], true);
                                }
                                
                                $order->add_meta_data($this->prefix.'debit_uuid', $gatewayReferenceId);
                                $order->delete_meta_data($this->prefix.'status');
                                $order->add_meta_data($this->prefix.'status', 'debited', true);
                                $order->save_meta_data();
                                
                                $comment1 = __('Allsecure Exchange payment is successfully debited. ', $this->domain);
                                $comment2 = __('Transaction ID', $this->domain).': ' .$gatewayReferenceId;
                                $comment = $comment1.$comment2;

                                $order_status = $this->getOrderStatusBySlug($this->success_order_status);
                                $order->update_status($order_status, $comment);
                            } else if ($callbackResult->getTransactionType() == AllsecureCallbackResult::TYPE_CAPTURE) {
                                //result capture
                                $order->add_meta_data($this->prefix.'capture_uuid', $gatewayReferenceId);
                                $order->delete_meta_data($this->prefix.'status');
                                $order->add_meta_data($this->prefix.'status', 'captured', true);
                                $order->save_meta_data();
                                
                                $comment1 = __('Allsecure Exchange payment is successfully captured. ', $this->domain);
                                $comment2 = __('Transaction ID', $this->domain).': ' .$gatewayReferenceId;
                                $comment = $comment1.$comment2;
                                
                                $order_status = $this->getOrderStatusBySlug($this->success_order_status);
                                $order->update_status($order_status, $comment);
                            } else if ($callbackResult->getTransactionType() == AllsecureCallbackResult::TYPE_VOID) {
                                //result void
                                $order->add_meta_data($this->prefix.'void_uuid', $gatewayReferenceId);
                                $order->delete_meta_data($this->prefix.'status');
                                $order->add_meta_data($this->prefix.'status', 'voided', true);
                                $order->save_meta_data();

                                $comment1 = __('Allsecure Exchange payment is successfully voided. ', $this->domain);
                                $comment2 = __('Transaction ID', $this->domain).': ' .$gatewayReferenceId;
                                $comment = $comment1.$comment2;
                                
                                $order_status = 'cancelled';
                                $order->update_status($order_status, $comment);
                            } else if ($callbackResult->getTransactionType() == AllsecureCallbackResult::TYPE_PREAUTHORIZE) {
                                //result preauthorize
                                if ( isset($callbackResult->getExtraData()['authCode']) ) {
                                    $order->add_meta_data($this->prefix.'AuthCode', $callbackResult->getExtraData()['authCode'], true);
                                } elseif (isset($callbackResult->getExtraData()['adapterReferenceId']) ) {
                                    $order->add_meta_data($this->prefix.'AuthCode', $callbackResult->getExtraData()['adapterReferenceId'], true);
                                }

                                $order->add_meta_data($this->prefix.'preauthorize_uuid', $gatewayReferenceId);
                                $order->delete_meta_data($this->prefix.'status');
                                $order->add_meta_data($this->prefix.'status', 'preauthorized', true);
                                $order->save_meta_data();
                                $order->payment_complete();

                                $comment1 = __('Allsecure payment is successfully reserved for manual capture. ', $this->domain);
                                $comment2 = __('Transaction ID', $this->domain).': ' .$gatewayReferenceId;
                                $comment = $comment1.$comment2;

                                $order_status = 'wc-authorised';
                                $order->update_status($order_status, $comment);
                            }
                        } elseif ($callbackResult->getResult() == AllsecureCallbackResult::RESULT_ERROR) {
                            //payment error
                            $order->delete_meta_data($this->prefix.'status');
                            $order->add_meta_data($this->prefix.'status', 'error', true);
                            $order->save_meta_data();
                            $error = $callbackResult->getFirstError();
                            $errorCode = $error->getCode();
                            if (empty($errorCode)) {
                                $errorCode = $error->getAdapterCode();
                            }

                            $comment1 = __('Error from gateway.', $this->domain);
                            $comment2 = $this->getErrorMessageByCode($errorCode);
                            $comment = $comment1.$comment2;
                            
                            $order_status = 'failed';
                            $order->update_status($order_status, $comment);
                        } else {
                            throw new \Exception(__('Unknown error', $this->domain));
                        }
                    } else {
                        throw new \Exception(__('No order data received', $this->domain));
                    }
                } else {
                    throw new \Exception(__('No order data received', $this->domain));
                }
                echo 'OK';
                exit;
            } catch (\Exception $e) {
                $this->log('Webhook Catch: '.$e->getMessage());
                echo 'OK';
                exit;
            }
        }
        
        /**
         * Built in function to render the payment description and payment form fields
         *
         * @return void
         */
        function payment_fields() { 
            wp_enqueue_script('allsecure_paymentjs');
            wp_enqueue_script('allsecure_exchange_js');
            
            if (isset($_REQUEST['egassemrorre']) && !empty($_REQUEST['egassemrorre']) )  {
                $egassemrorre = sanitize_text_field($_REQUEST['egassemrorre']);
                $message = base64_decode($egassemrorre);
            ?>
                <script>
                    let message = '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout"><div class="woocommerce-error"><?php echo $message?></div></div>';
                    jQuery(document).ready(function(){
                        jQuery('.woocommerce-notices-wrapper:first').html(message);
                    });
                </script>
            <?php
            }

            $display = false;
            if ($this->checkout_mode == 'paymentjs' || !empty($this->description)) {
                $display = true;
            } else {
                ?>
                <style>.payment_box.payment_method_allsecureexchange {display: none !important;}</style>
                <?php
            }
            
            if ($display) {
            ?>
            <script>
                window.public_integration_key = '<?php echo $this->integration_key?>';
                window.card_supported = '<?php echo strtolower(implode(',',$this->card_supported))?>';
                
                <?php if ($this->enable_installment) { ?>
                    window.installment_bins = '<?php echo strtolower(implode(',',$this->installment_bins))?>';
                    window.allowed_installments = new Array();
                    <?php
                    if (count($this->installment_bins) > 0) {
                        foreach($this->installment_bins as $key => $val) {
                        ?>
                            window.allowed_installments['<?php echo $val?>'] = '<?php echo $this->allowed_installments[$key]?>';
                        <?php
                        }
                    }
                    ?>
                <?php } ?>
            </script>
            <script type="text/javascript" src="<?php echo ALLSECUREEXCHANGE_PLUGIN_URL.'/assets/js/allsecure-exchange.js?ver=' . ALLSECUREEXCHANGE_VERSION?>"></script>
            <script type="text/javascript" src="<?php echo ALLSECUREEXCHANGE_PLUGIN_URL.'/assets/js/allsecure-exchange-validator.js?ver=' . ALLSECUREEXCHANGE_VERSION?>"></script>
            <div class="form-row form-row-wide">
                <?php if (!empty($this->description)) {?>
                <p><?php echo $this->description; ?></p>
                <?php } ?>

                <div id="allsecure-payment-form">
                    <input type="hidden" id="allsecurepay_transaction_token" name="allsecurepay_transaction_token" />
                    <p><strong><?php echo __('Credit Card Information', $this->domain)?></strong></p>
                    <p class="form-row form-row-wide">
                        <label for="allsecurepay_cc_name" class=""><?php echo __('Card holder', $this->domain)?>&nbsp;<abbr class="required" title="required">*</abbr></label>
                        <span class="woocommerce-input-wrapper">
                            <input type="text" class="input-text " name="allsecurepay_cc_name" id="allsecurepay_cc_name" />
                        </span>
                        <span id="allsecurepay_cc_name-required-error" class="invalid-feedback">
                            <?php echo __('This is a required field.', $this->domain)?>
                        </span>
                        <span id="allsecurepay_cc_name-invalid-error" class="invalid-feedback">
                            <?php echo __('Please enter a valid card holder name in this field.', $this->domain)?>
                        </span>
                    </p>
                    <p class="form-row form-row-wide">
                        <label for="allsecurepay_cc_number" class=""><?php echo __('Card Number', $this->domain)?>&nbsp;<abbr class="required" title="required">*</abbr></label>
                        <span class="woocommerce-input-wrapper" id="allsecurepay_cc_number" style="">
                            <img src="<?php echo ALLSECUREEXCHANGE_PLUGIN_URL?>/assets/images/loadingAnimation.gif" class="allsecurepay-field-loader" />
                        </span>
                        <span id="allsecurepay_cc_number-error" class="invalid-feedback">
                            <?php echo __('Please enter a valid number in this field.', $this->domain)?>
                        </span>
                        <span id="allsecurepay_cc_number-not-supported-error" class="invalid-feedback">
                            <?php echo __('This card type is not supported.', $this->domain)?>
                        </span>
                    </p>
                    <p class="form-row form-row-first">
                        <label for="allsecurepay_expiration_date" class=""><?php echo __('Expiration Date', $this->domain)?>&nbsp;<abbr class="required" title="required">*</abbr></label>
                        <span class="woocommerce-input-wrapper">
                            <input type="hidden" id="allsecurepay_expiration_month">
                            <input type="hidden" id="allsecurepay_expiration_year">
                            <input type="text" class="input-text " id="allsecurepay_expiration_date"
                                class="form-control" 
                                maxlength = "5"
                                inputmode="tel"
                                placeholder="--/--"
                                autocomplete="off"
                             />
                        </span>
                        <span id="allsecurepay_expiration-required-error" class="invalid-feedback">
                            <?php echo __('This is a required field.', $this->domain)?>
                        </span>
                        <span id="allsecurepay_expiration-invalid-error" class="invalid-feedback">
                            <?php echo __('Incorrect credit card expiration date.', $this->domain)?>
                        </span>
                    </p>
                    <p class="form-row form-row-last">
                        <label for="allsecurepay_cc_cvv" class=""><?php echo __('CVV', $this->domain)?>&nbsp;<abbr class="required" title="required">*</abbr></label>
                        <span class="woocommerce-input-wrapper" id="allsecurepay_cc_cvv">
                            <img src="<?php echo ALLSECUREEXCHANGE_PLUGIN_URL?>/assets/images/loadingAnimation.gif" class="allsecurepay-field-loader" />
                        </span>
                        <span id="allsecurepay_cc_cvv-error" class="invalid-feedback">
                            <?php echo __('Please enter a valid number in this field.', $this->domain)?>
                        </span>
                    </p>
                    <?php if ($this->enable_installment) { ?>
                    <p class="form-row form-row-wide" id="allsecurepay_pay_installment_container" style="display: none">
                        <label for="allsecurepay_pay_installment" class="">
                            <input type="checkbox" class="input-checkbox " name="allsecurepay_pay_installment" id="allsecurepay_pay_installment" />
                             <span class="woocommerce-input-wrapper">
                                <?php echo __('Pay in Installments', $this->domain)?>
                             </span>
                        </label>
                    </p>
                    <p class="form-row form-row-wide" id="allsecurepay_installment_number_container" style="display: none">
                        <label for="allsecurepay_installment_number" class=""><?php echo __('Select No. of installments', $this->domain)?></label>
                        <span class="woocommerce-input-wrapper">
                            <select name="allsecurepay_installment_number" id="allsecurepay_installment_number" style="width:100px;  min-height: 35px; text-align: center">
                                
                            </select>
                        </span>
                    </p>
                    <?php } ?>
                </div>
            </div>
            <?php
            }
        }
        
        /**
         * Add transaction details to the order received page.
         * 
         * @param string $str
         * @param object $order
         * 
         * @return void
         */
        public function thankyou_page($str, $order) {
            echo $str;
            
            if ($this->transaction_confirmation_page) {
                try {
                    $order_id = false;
                    if (isset($_GET['order_id'])) {
                        $order_id = (int)sanitize_text_field($_GET['order_id']);
                    }

                    if ($order_id) {
                        $order = new WC_Order($order_id);
                        if ($order->get_payment_method() == $this->id) {
                            $uuid = $order->get_meta($this->prefix.'payment_request_uuid');

                            $client = $this->getClient();

                            $statusRequestData = new StatusRequestData();
                            $statusRequestData->setUuid($uuid);
                            $statusResult = $client->sendStatusRequest($statusRequestData);

                            $params = array();
                            if ($statusResult->hasErrors()) {

                            } else {
                                $result = $statusResult->getTransactionStatus();

                                $transactionType = $statusResult->getTransactionType();
                                $amount = $statusResult->getAmount();
                                $currency = $statusResult->getCurrency();
                                $cardData = $statusResult->getreturnData();
                                $cardHolder = $cardData->getcardHolder();
                                $binBrand = strtoupper($cardData->getType());
                                $expiryMonth = $cardData->getexpiryMonth();
                                $expiryYear = $cardData->getexpiryYear();
                                $firstSixDigits = $cardData->getfirstSixDigits();
                                $lastFourDigits = $cardData->getlastFourDigits();
                                $transactionId = $statusResult->getTransactionUuid() ?? NULL;
                                $extraData = $statusResult->getextraData();

                                if ( isset($extraData['authCode']) ) {
                                    $authCode = $extraData['authCode'];
                                } elseif (isset($extraData['adapterReferenceId']) ) {
                                    $authCode = $extraData['adapterReferenceId'];
                                } else {
                                    $authCode = NULL;
                                }
                                $timestamp = date("Y-m-d H:i:s");
                                
                                $installment_number = $order->get_meta($this->prefix.'installment_number');

                                ?>
                                <div class='woocommerce-order'>
                                    <h5><?php echo __('Transaction details', $this->domain)?>: </h5>
                                    <ul class='woocommerce-order-overview woocommerce-thankyou-order-details order_details'>
                                            <?php if ($authCode) { ?>
                                            <li class='woocommerce-order-overview__email email'>
                                                <?php echo __('Transaction Codes', $this->domain)?>: <strong><?php echo $authCode ?></strong>
                                            </li>
                                            <?php } ?>
                                            <li class='woocommerce-order-overview__email email'>
                                                <?php echo __('Transaction ID', $this->domain)?>: <strong><?php echo $transactionId?></strong>
                                            </li>
                                            <li class='woocommerce-order-overview__email email'>
                                                <?php echo __('Card Type', $this->domain)?>: <strong><?php echo $binBrand ." *** ".$lastFourDigits?></strong>
                                            </li>
                                            <li class='woocommerce-order-overview__email email'>
                                                <?php echo __('Payment Type', $this->domain)?>: <strong><?php echo $transactionType?></strong>
                                            </li>
                                            <li class='woocommerce-order-overview__email email'>
                                                <?php echo __('Currency', $this->domain)?>: <strong><?php echo $currency?></strong>
                                            </li>
                                            <li class='woocommerce-order-overview__email email'>
                                                <?php echo __('Amount Paid', $this->domain)?>: <strong><?php echo $amount?></strong>
                                            </li>
                                            <?php if (!empty($installment_number)) {?>
                                            <li class='woocommerce-order-overview__email email'>
                                                <?php echo __('Chose to make payment in ', $this->domain)?> <strong><?php echo $installment_number?> <?php echo __('installments', $this->domain)?></strong>
                                            </li>
                                            <?php } ?>
                                            <li class='woocommerce-order-overview__email email'>
                                                <?php echo __('Transaction Time', $this->domain)?>: <strong><?php echo $timestamp?></strong>
                                            </li>
                                            
                                    </ul>
                                </div>
                                <?php
                            }
                        }
                    } 
                } catch (\Exception $e) {

                }
            }
        }
        
        /**
         * Add transaction details to the order email
         * 
         * @param object $order
         * 
         * @return void
         */
	function email_after_order_table($order) {
            if ($order->get_payment_method() == $this->id && $this->transaction_email) {
                try {
                    $order_id = $order->get_id();

                    if ($order_id) {
                        $order = new WC_Order($order_id);
                        $uuid = $order->get_meta($this->prefix.'payment_request_uuid');

                        $client = $this->getClient();

                        $statusRequestData = new StatusRequestData();
                        $statusRequestData->setUuid($uuid);
                        $statusResult = $client->sendStatusRequest($statusRequestData);

                        $params = array();
                        if ($statusResult->hasErrors()) {

                        } else {
                            $result = $statusResult->getTransactionStatus();
                            $transactionType = $statusResult->getTransactionType();
                            $amount = $statusResult->getAmount();
                            $currency = $statusResult->getCurrency();
                            $cardData = $statusResult->getreturnData();
                            $cardHolder = $cardData->getcardHolder();
                            $binBrand = strtoupper($cardData->getType());
                            $expiryMonth = $cardData->getexpiryMonth();
                            $expiryYear = $cardData->getexpiryYear();
                            $firstSixDigits = $cardData->getfirstSixDigits();
                            $lastFourDigits = $cardData->getlastFourDigits();
                            $transactionId = $statusResult->getTransactionUuid() ?? NULL;
                            $extraData = $statusResult->getextraData();

                            if ( isset($extraData['authCode']) ) {
                                $authCode = $extraData['authCode'];
                            } elseif (isset($extraData['adapterReferenceId']) ) {
                                $authCode = $extraData['adapterReferenceId'];
                            } else {
                                $authCode = NULL;
                            }
                            $timestamp = date("Y-m-d H:i:s");
                            
                            $installment_number = $order->get_meta($this->prefix.'installment_number');
                            
                            ?>
                            <div class='woocommerce-order'>
                                <h5><?php echo __('Transaction details', $this->domain)?>: </h5>
                                <ul class='woocommerce-order-overview woocommerce-thankyou-order-details order_details'>
                                        <?php if ($authCode) { ?>
                                        <li class='woocommerce-order-overview__email email'>
                                            <?php echo __('Transaction Codes', $this->domain)?>: <strong><?php echo $authCode ?></strong>
                                        </li>
                                        <?php } ?>
                                        <li class='woocommerce-order-overview__email email'>
                                            <?php echo __('Transaction ID', $this->domain)?>: <strong><?php echo $transactionId?></strong>
                                        </li>
                                        <li class='woocommerce-order-overview__email email'>
                                            <?php echo __('Card Type', $this->domain)?>: <strong><?php echo $binBrand ." *** ".$lastFourDigits?></strong>
                                        </li>
                                        <li class='woocommerce-order-overview__email email'>
                                            <?php echo __('Payment Type', $this->domain)?>: <strong><?php echo $transactionType?></strong>
                                        </li>
                                        <li class='woocommerce-order-overview__email email'>
                                            <?php echo __('Currency', $this->domain)?>: <strong><?php echo $currency?></strong>
                                        </li>
                                        <li class='woocommerce-order-overview__email email'>
                                            <?php echo __('Amount Paid', $this->domain)?>: <strong><?php echo $amount?></strong>
                                        </li>
                                        <?php if (!empty($installment_number)) {?>
                                        <li class='woocommerce-order-overview__email email'>
                                            <?php echo __('Chose to make payment in ', $this->domain)?> <strong><?php echo $installment_number?> <?php echo __('installments', $this->domain)?></strong>
                                        </li>
                                        <?php } ?>
                                        <li class='woocommerce-order-overview__email email'>
                                            <?php echo __('Transaction Time', $this->domain)?>: <strong><?php echo $timestamp?></strong>
                                        </li>
                                </ul>
                            </div>
                            <?php
                        }
                    } 
                } catch (\Exception $e) {

                }
            }
	}
        
        /**
         * Add Capture, Void and Refund Buttons on the admin order page
         * 
         * @param int $order_id
         * 
         * @return void 
         */
        public function admin_order_totals( $order_id ) {
            $order = new WC_Order($order_id);
            if ($order->get_payment_method().'1' == $this->id) {

                ?>
                <div id="allsecure_payment_buttons">

                </div>
                
                <script type="text/javascript">

                jQuery(document).ready(function(){

                });
                </script>
                <?php
            }
        }
        
        /**
         * Add Capture, Void and Refund Buttons on the admin order page
         * 
         * @param object $order
         * 
         * @return void 
         */
        public function admin_order_action_buttons($order) {
            if ($order->get_payment_method() == $this->id) {
                $order_id = $order->get_id();
                $capture = false;
                $void = false;
                $refund = 0;
                
                $statuses = $order->get_meta($this->prefix.'status', 0);
                if (count($statuses) > 1) {
                    $i = 0;
                    $j = count($statuses)-1;
                    foreach ($statuses as $statusObj) {
                         if ($i == $j) {
                            $status = $statusObj->value;
                            $order->delete_meta_data($this->prefix.'status');
                            $order->add_meta_data($this->prefix.'status', $status, true);
                            $order->save_meta_data();
                        }
                        $i++;
                    }
                } else {
                    $status = $order->get_meta($this->prefix.'status');
                }
                
                if ($status == 'debited' || $status == 'captured') {
                    $refund = 1;
                } elseif ($status == 'preauthorized' ) {
                    $capture = true;
                    $void = true;
                }
                
                ?>
                <?php if ($capture) {?>
                <button type="button" class="button allsecure_capture"><?php echo __('Capture', $this->domain)?></button>
                <?php }?>
                <?php if ($void) {?>
                <button type="button" class="button allsecure_void"><?php echo __('Void', $this->domain)?></button>
                <?php }?>

                <script type="text/javascript">
                var allsecureRefund = <?php echo $refund?>;
                let capture_ajax_url = '<?php echo $this->getUrl('capture', $order_id);?>';
                let void_ajax_url = '<?php echo $this->getUrl('void', $order_id);?>';
                let loaderText = '<?php echo __('Processing...', $this->domain)?>';
                let confirmText = '<?php echo __('Are you sure you wish to process this void? This action cannot be undone', $this->domain)?>';
                
                jQuery(document).ready(function(){
                    if (allsecureRefund == 0) {
                        jQuery('.button.refund-items').hide();
                    }
                    
                    jQuery('.allsecure_capture').click(function(){
                        var originalText = jQuery('.button.allsecure_capture').html();
                        jQuery('.button.allsecure_capture').html(loaderText);
                        jQuery.getJSON(capture_ajax_url, function (data) {
                            if (data.status == 'error') {
                                jQuery('.button.allsecure_capture').html(originalText);
                                alert(data.message);
                            } else if (data.status == 'success') {
                                jQuery('.button.allsecure_capture').hide();
                                jQuery('.button.allsecure_void').hide();
                                alert(data.message);
                                window.location.href=location.href;
                            }
                        });
                    });
                    
                     jQuery('.allsecure_void').click(function(){
                        if (confirm(confirmText) == true) {
                            var originalText = jQuery('.button.allsecure_void').html();
                            jQuery('.button.allsecure_void').html(loaderText);
                            jQuery.getJSON(void_ajax_url, function (data) {
                                if (data.status == 'error') {
                                    jQuery('.button.allsecure_void').html(originalText);
                                    alert(data.message);
                                } else if (data.status == 'success') {
                                    jQuery('.button.allsecure_capture').hide();
                                    jQuery('.button.allsecure_void').hide();
                                    alert(data.message);
                                    window.location.href=location.href;
                                }
                            });
                        }
                    });
                });
                </script>
                <?php
            }
        }
        
        /**
         * Refund payment
         * 
         * @param int $order_id
         * @param mixed $amount
         * @param string $reason
         * 
         * @return mixed 
         */
        public function process_refund($order_id, $amount = NULL, $reason = '') {
            $order = new WC_Order($order_id);
            $amount = sanitize_text_field($amount);
            $order_data = $order->get_data();
            $order_total_paid = $order->get_total();
            
            try {
                $this->log('Refund triggered');
                
                if ($amount <= 0 ) {
                    throw new Exception(__('Refund amount shoule be greater than 0.',  $this->domain));
                }
                
                if ($amount < $order_total_paid) {
                    throw new Exception(__('Amount shoule be equal to order paid total.',  $this->domain));
                }
                
                $transaction_id = $order->get_meta($this->prefix.'transaction_id');
                $merchantTransactionId = 'refund-'.$this->encodeOrderId($order_id);

                $client = $this->getClient();

                $refund = new AllsecureRefund();
                $refund->setMerchantTransactionId($merchantTransactionId)
                        ->setAmount($amount)
                        ->setCurrency($order_data['currency'])
                        ->setReferenceUuid($transaction_id);

                $this->log('Refund request');
                $this->log((array)($refund));
                $result = $client->refund($refund);
                $this->log('Refund response');
                $this->log((array)($result));

                if ($result->getReturnType() == AllsecureResult::RETURN_TYPE_FINISHED) {
                    $gatewayReferenceId = $result->getUuid();
                    $order->delete_meta_data($this->prefix.'status');
                    $order->add_meta_data($this->prefix.'status', 'refunded', true);
                    $order->add_meta_data($this->prefix.'transaction_id', $gatewayReferenceId, true);
                    $order->add_meta_data($this->prefix.'refund_uuid', $gatewayReferenceId, true);
                    $order->save_meta_data();
                    
                    $comment1 = __('Allsecure Exchange payment is successfully refunded. ', $this->domain);
                    $comment2 = __('Transaction ID', $this->domain).': ' .$gatewayReferenceId;
                    $comment = $comment1.$comment2;
                    $order->update_status('refunded', $comment);
                    return true;
                } elseif ($result->getReturnType() == AllsecureResult::RETURN_TYPE_ERROR) {
                    $error = $result->getFirstError();
                    $errorCode = $error->getCode();
                    if (empty($errorCode)) {
                        $errorCode = $error->getAdapterCode();
                    }
                    $errorMessage = $this->getErrorMessageByCode($errorCode);
                    throw new \Exception($errorMessage);
                } else {
                    throw new \Exception(__('Unknown error', $this->domain));
                }
            } catch (\Exception $e) {
                $message = $e->getMessage();
                $this->log('Refund Catch: '.$message);
                return new WP_Error(400, $message);
            }
        }
        
        /**
         * Capture payment
         * 
         * @return mixed 
         */
        public function process_capture() {
            $status = 'unknown';
            $message = '';
            
            try {
                $this->log('Capture triggered');
                $order_id = false;
                if (isset($_GET['order_id'])) {
                    $order_id = (int)sanitize_text_field($_GET['order_id']);
                }

                if ($order_id) {
                    $order = new WC_Order($order_id);
                    
                    $order_data = $order->get_data();
                    $order_total_paid = $order->get_total();
            
                    $transaction_id = $order->get_meta($this->prefix.'transaction_id');
                    $merchantTransactionId = 'capture-'.$this->encodeOrderId($order_id);

                    $client = $this->getClient();
                    
                    $capture = new AllsecureCapture();
                    $capture->setTransactionId($merchantTransactionId)
                        ->setAmount($order_total_paid)
                        ->setCurrency($order_data['currency'])
                        ->setReferenceTransactionId($transaction_id);

                    $this->log('Capture request');
                    $this->log((array)($capture));
                    $result = $client->Capture($capture);
                    $this->log('Capture response');
                    $this->log((array)($result));

                    if ($result->getReturnType() == AllsecureResult::RETURN_TYPE_FINISHED) {
                        $gatewayReferenceId = $result->getUuid();
                        $order->delete_meta_data($this->prefix.'status');
                        $order->add_meta_data($this->prefix.'status', 'captured', true);
                        $order->add_meta_data($this->prefix.'transaction_id', $gatewayReferenceId, true);
                        $order->add_meta_data($this->prefix.'capture_uuid', $gatewayReferenceId, true);
                        $order->save_meta_data();

                        $comment1 = __('Allsecure Exchange payment is successfully captured. ', $this->domain);
                        $comment2 = __('Transaction ID', $this->domain).': ' .$gatewayReferenceId;
                        $comment = $comment1.$comment2;
                        
                        $order_status = $this->getOrderStatusBySlug($this->success_order_status);
                        $order->update_status($order_status, $comment);
                        
                        $status = 'success';
                        $message = $comment;
                    } elseif ($result->getReturnType() == AllsecureResult::RETURN_TYPE_ERROR) {
                        $error = $result->getFirstError();
                        $errorCode = $error->getCode();
                        if (empty($errorCode)) {
                            $errorCode = $error->getAdapterCode();
                        }
                        $errorMessage = $this->getErrorMessageByCode($errorCode);
                        throw new \Exception($errorMessage);
                    } else {
                        throw new \Exception(__('Unknown error', $this->domain));
                    }
                } else {
                    throw new \Exception(__('No order data received', $this->domain));
                }
            } catch (\Exception $e) {
                $status = 'error';
                $message = $e->getMessage();
                $this->log('Capture Catch: '.$message);
            }
            
            $data = [
                'status' => $status,
                'message' => $message
            ];

            echo json_encode($data);
            die();
        }
        
        /**
         * Void payment
         * 
         * @return mixed 
         */
        public function process_void() {
            $status = 'unknown';
            $message = '';
            
            try {
                $this->log('Void triggered');
                $order_id = false;
                if (isset($_GET['order_id'])) {
                    $order_id = (int)sanitize_text_field($_GET['order_id']);
                }

                if ($order_id) {
                    $order = new WC_Order($order_id);

                    $transaction_id = $order->get_meta($this->prefix.'transaction_id');
                    $merchantTransactionId = 'void-'.$this->encodeOrderId($order_id);

                    $client = $this->getClient();
                    
                    $void = new AllsecureVoidTransaction();
                    $void->setMerchantTransactionId($merchantTransactionId)
                         ->setReferenceUuid($transaction_id);

                    $this->log('Void request');
                    $this->log((array)($void));
                    $result = $client->void($void);
                    $this->log('Void response');
                    $this->log((array)($void));

                    if ($result->getReturnType() == AllsecureResult::RETURN_TYPE_FINISHED) {
                        $gatewayReferenceId = $result->getUuid();
                        $order->delete_meta_data($this->prefix.'status');
                        $order->add_meta_data($this->prefix.'status', 'voided', true);
                        $order->add_meta_data($this->prefix.'transaction_id', $gatewayReferenceId, true);
                        $order->add_meta_data($this->prefix.'void_uuid', $gatewayReferenceId, true);
                        $order->save_meta_data();

                        $comment1 = __('Allsecure Exchange payment is successfully voided. ', $this->domain);
                        $comment2 = __('Transaction ID', $this->domain).': ' .$gatewayReferenceId;
                        $comment = $comment1.$comment2;
                        
                        $order_status = 'cancelled';
                        $order->update_status($order_status, $comment);
                        
                        $status = 'success';
                        $message = $comment;
                    } elseif ($result->getReturnType() == AllsecureResult::RETURN_TYPE_ERROR) {
                        $error = $result->getFirstError();
                        $errorCode = $error->getCode();
                        if (empty($errorCode)) {
                            $errorCode = $error->getAdapterCode();
                        }
                        $errorMessage = $this->getErrorMessageByCode($errorCode);
                        throw new \Exception($errorMessage);
                    } else {
                        throw new \Exception(__('Unknown error', $this->domain));
                    }
                } else {
                    throw new \Exception(__('No order data received', $this->domain));
                }
            } catch (\Exception $e) {
                $status = 'error';
                $message = $e->getMessage();
                $this->log('Void Catch: '.$message);
            }
            
            $data = [
                'status' => $status,
                'message' => $message
            ];

            echo json_encode($data);
            die();
        }
        
        /**
        * Add JS files to the frontend
        */
        public function load_front_assets() {
            if ( is_checkout() ) {
                wp_enqueue_style( 'hitpay-css', ALLSECUREEXCHANGE_PLUGIN_URL.'/assets/css/front.css', array(), ALLSECUREEXCHANGE_VERSION, 'all' );
                if ($this->checkout_mode == 'paymentjs') {
                    $payment_js = 'https://asxgw.com/js/integrated/payment.1.3.min.js';
                    if ($this->operation_mode == 'test') {
                        $payment_js = 'https://asxgw.paymentsandbox.cloud/js/integrated/payment.1.3.min.js';
                    }
                    wp_register_script('allsecure_paymentjs', $payment_js, [], ALLSECUREEXCHANGE_VERSION, false);
                    wp_register_script('allsecure_exchange_js', ALLSECUREEXCHANGE_PLUGIN_URL.'/assets/js/allsecure-exchange.js', [], ALLSECUREEXCHANGE_VERSION, false);
                }
            }  
        }
        
        /**
        * Add attribute data-main="payment-js" to script tag of paymentjs javascript file
        *
        * @param mixed $tag
        * @param mixed $handle
        * @param mixed $src
        * 
        * @return string
        */
        public function add_paymentjs_tag($tag, $handle, $src) {
            if('allsecure_paymentjs' !== $handle) {
                return $tag;
            }
            return str_replace(' src', ' data-main="payment-js" src', $tag);
        }

        /**
        * Log for debugging
        *
        * @param mixed $content
        * 
        * @return string
        */
        public function log($content)
        {
            $debug = $this->debug;
            if ($debug == 'yes') {
                $file = ALLSECUREEXCHANGE_PLUGIN_PATH.'debug.log';
                try {
                    $fp = fopen($file, 'a+');
                    if ($fp) {
                        fwrite($fp, "\n");
                        fwrite($fp, date("Y-m-d H:i:s").": ");
                        fwrite($fp, print_r($content, true));
                        fclose($fp);
                    }
                } catch (\Exception $e) {}
            }
        }
        
        /**
        * Get Order Statuses
        *
        * @return string
        */
        public function getOrderStatuses()
        {
            $statuses = wc_get_order_statuses();
            unset($statuses['wc-pending']);
            unset($statuses['wc-cancelled']);
            unset($statuses['wc-refunded']);
            unset($statuses['wc-failed']);
            unset($statuses['wc-on-hold']);
            return $statuses;
        }
        
        /**
        * Get Order Status Slug
        *
        * @param string $status 
        * 
        * @return string
        */
        public function getOrderStatusBySlug($status)
        {
            return str_replace('wc-', '', $status);
        }
        
        /**
        * Get URL
        *
        * @param string $action 
        * @param int $order_id 
        *
        * @return string
        */
        public function getUrl($action, $order_id)
        {
            return add_query_arg(
                array(
                    'wc-api' => 'wc_' . $this->id,
                    'action' => $action,
                    'order_id' => $order_id
                ),
                home_url('/')
            );
        }
        
        /**
        * Get Allsecure API Client
        *
        * @return AllsecureClient
        */

       public function getClient()
       {
           $testMode = false;
           if ($this->operation_mode == 'test') {
               $testMode = true;
           }

           if ($testMode) {
               AllsecureClient::setApiUrl($this->getTestApiUrl());
           } else {
               AllsecureClient::setApiUrl($this->getLiveApiUrl());
           }

           $api_user = $this->api_user;
           if (!empty($api_user)) {
               $api_user = trim($api_user);
           }

           $api_password = $this->api_password;
           if (!empty($api_password)) {
               $api_password = htmlspecialchars_decode($api_password);
           }

           $api_key = $this->api_key;
           if (!empty($api_key)) {
               $api_key = trim($api_key);
           }

           $api_secret = $this->shared_secret;
           if (!empty($api_secret)) {
               $api_secret = trim($api_secret);
           }

           $client = new AllsecureClient(
               $api_user, 
               $api_password, 
               $api_key,
               $api_secret, 
               null,
               $testMode
           );

           return $client;
       }

       /**
        * Get Test API URL
        *
        * @return string
        */
       public function getTestApiUrl()
       {
           return 'https://asxgw.paymentsandbox.cloud/';
       }

       /**
        * Get Live API URL
        *
        * @return string
        */
       public function getLiveApiUrl()
       {
           return 'https://asxgw.com/';
       }

       /**
        * Get Encoded Order Id
        *
        * @param string $orderId
        * return string
        */
       public function encodeOrderId($orderId)
       {
           return $orderId . '-' . date('YmdHis') . substr(sha1(uniqid()), 0, 10);
       }

       /**
        * Get Decoded Order Id
        *
        * @param string $orderId
        * return string
        */
       public function decodeOrderId($orderId)
       {
           if (strpos($orderId, '-') === false) {
               return $orderId;
           }

           $orderIdParts = explode('-', $orderId);

           if(count($orderIdParts) === 2) {
               $orderId = $orderIdParts[0];
           }

           if(count($orderIdParts) === 3) {
               $orderId = $orderIdParts[1];
           }

           return $orderId;
       }

       /**
        * Process Transaction
        *
        * @param $order
        * @param string $token
        * @param string $action
        * 
        * @return $this
        */
       public function processTransaction($order, $token, $action, $installment_number='')
       {
           $client = $this->getClient();
           $order_data = $order->get_data();

           $customer = new AllsecureCustomer();
           $customer->setFirstName($order_data['billing']['first_name'])
                   ->setLastName($order_data['billing']['last_name'])
                   ->setEmail($order_data['billing']['email'])
                   ->setBillingAddress1($order_data['billing']['address_1'])
                   ->setBillingCity($order_data['billing']['city'])
                   ->setBillingCountry($order_data['billing']['country'])
                   ->setBillingPhone($order_data['billing']['phone'])
                   ->setBillingPostcode($order_data['billing']['postcode'])
                   ->setBillingState($order_data['billing']['state'])
                   ->setIpAddress($order->get_customer_ip_address());

           if (!empty($order_data['billing']['address_2'])) {
               $customer->setBillingAddress2($order_data['billing']['address_2']);
           }

           if (!empty($order_data['billing']['company'])) {
               $customer->setCompany($order_data['billing']['company']);
           }

           if ($order->get_shipping_country()) {
               $customer->setShippingFirstName($order_data['shipping']['first_name'])
                   ->setShippingLastName($order_data['shipping']['last_name'])    
                   ->setShippingAddress1($order_data['shipping']['address_1'])
                   ->setShippingCity($order_data['shipping']['city'])
                   ->setShippingCountry($order_data['shipping']['country'])
                   ->setShippingPhone($order_data['shipping']['phone'])
                   ->setShippingPostcode($order_data['shipping']['postcode'])
                   ->setShippingState($order_data['shipping']['state']);

               if (!empty($order_data['shipping']['address_2'])) {
                   $customer->setShippingAddress2($order_data['shipping']['address_2']);
               }

               if (!empty($order_data['shipping']['company'])) {
                   $customer->setShippingCompany($order_data['shipping']['company']);
               }
           }

           $amount = floatval($order->get_total());
           $amount = round($amount, 2);

           if ($action == 'debit') {
               $transasction = new AllsecureDebit();
           } else {
               $transasction = new AllsecurePreauthorize();
           }

           $order_id = $order->get_id();
           $merchantTransactionId = $this->encodeOrderId($order_id);
           
           $return_url = $this->getUrl('return', $order_id);
           $webhook_url = $this->getUrl('webhook', $order_id);
           $error_url = $this->getUrl('error', $order_id);
           $cancel_url = $this->getUrl('cancel', $order_id);

           $transasction->setMerchantTransactionId($merchantTransactionId)
               ->setAmount($amount)
               ->setCurrency($order_data['currency'])
               ->setCustomer($customer)
               ->setCallbackUrl($webhook_url)
               ->setCancelUrl($cancel_url)
               ->setSuccessUrl($return_url)
               ->setErrorUrl($error_url);

           if (isset($token)) {
               $transasction->setTransactionToken($token);
           }
           
           if (!empty($installment_number)) {
               $extraData = ['installment' => $installment_number];
               $transasction->setExtraData($extraData);
           }

           if ($action == 'debit') {
               $this->log('Debit Transaction');
               $this->log((array)($transasction));
               $result = $client->debit($transasction);
           } else {
               $this->log('PreAuthorize Transaction');
               $this->log((array)($transasction));
               $result = $client->preauthorize($transasction);
           }

           return $result;
       }

       /**
        * Debit Transaction
        *
        * @param $order
        * @param string $token
        * 
        * @return $this
        */
       public function debitTransaction($order, $token, $installment_number)
       {
           return @$this->processTransaction($order, $token, 'debit', $installment_number);
       }

       /**
        * Preauthorize Transaction
        *
        * @param $order
        * @param string $token
        * 
        * @return $this
        */
       public function preauthorizeTransaction($order, $token)
       {
           return @$this->processTransaction($order, $token, 'preauthorize');
       }
       
        /**
         * Get error message by error code
         *
         * @param string $code
         * 
         * @return string
         */
        public function getErrorMessageByCode($code)
        {
            $unknownError = __('Unknown error', $this->domain);
            $errors = array(
                '1000' => __('CONFIG ERROR. Some fundamental error in your request', $this->domain),
                '1001' => __('CONFIG ERROR. The upstream system responded with an unknown response', $this->domain),
                '1002' => __('CONFIG ERROR. Request data are malformed or missing', $this->domain),
                '1003' => __('CONFIG ERROR. Transaction could not be processed', $this->domain),
                '1004' => __('CONFIG ERROR. The request signature you provided was wrong', $this->domain),
                '1005' => __('CONFIG ERROR. The XML you provided was malformed or invalid', $this->domain),
                '1006' => __('CONFIG ERROR. Preconditions failed, e.g. capture on a failed authorize', $this->domain),
                '1007' => __('CONFIG ERROR. Something is wrong your configuration, please contact your integration engineer', $this->domain),
                '1008' => __('CONFIG ERROR. Unexpected system error', $this->domain),
                '9999' => __('CONFIG ERROR. We received an error which is not (yet) mapped to a better error code', $this->domain),
                '2001' => __('Account closed. The customer cancelled permission for his payment instrument externally', $this->domain),
                '2002' => __('User cancelled. Transaction was cancelled by customer', $this->domain),
                '2003' => __('Transaction declined. Please try again later or change the card', $this->domain),
                '2004' => __('Quota regulation. Card limit reached'),
                '2005' => __('Transaction expired. Customer took to long to submit his payment info', $this->domain),
                '2006' => __('Insufficient funds. Card limit reached', $this->domain),
                '2007' => __('Incorrect payment info. Double check and try again', $this->domain),
                '2008' => __('Invalid card. Try with some other card', $this->domain),
                '2009' => __('Expired card. Try with some other card', $this->domain),
                '2010' => __('Invalid card. Call your bank immediately', $this->domain),
                '2011' => __('Unsupported card. Try with some other card', $this->domain),
                '2012' => __('Transaction cancelled', $this->domain),
                '2013' => __('Transaction declined. Please try again later or call your bank', $this->domain),
                '2014' => __('Transaction declined. Please try again later or call your bank', $this->domain),
                '2015' => __('Transaction declined. Please try again later or call your bank', $this->domain),
                '2016' => __('Transaction declined. Please try again later or call your bank', $this->domain),
                '2017' => __('Invalid IBAN. Double check and try again', $this->domain),
                '2018' => __('Invalid BIC. Double check and try again', $this->domain),
                '2019' => __('Customer data invalid. Double check and try again', $this->domain),
                '2020' => __('CVV required. Double check and try again', $this->domain),
                '2021' => __('3D-Secure Verification failed. Please call your bank or try with a non 3-D Secure card', $this->domain),
                '3001' => __('COMMUNICATION PROBLEM. Timeout. Try again after a short pause', $this->domain),
                '3002' => __('COMMUNICATION PROBLEM. Transaction not allowed', $this->domain),
                '3003' => __('COMMUNICATION PROBLEM. System temporary unavailable. Try again after a short pause', $this->domain),
                '3004' => __('Duplicate transaction ID', $this->domain),
                '3005' => __('COMMUNICATION PROBLEM. Try again after a short pause', $this->domain),
                '7001' => __('Schedule request is invalid', $this->domain),
                '7002' => __('Schedule request failed', $this->domain),
                '7005' => __('Schedule action is not valid', $this->domain),
                '7010' => __('RegistrationId is required', $this->domain),
                '7020' => __('RegistrationId is not valid', $this->domain),
                '7030' => __('The registrationId must point to a "register", "debit+register" or "preauth+register"', $this->domain),
                '7035' => __('Initial transaction is not a "register", "debit+register" or "preauth+register"', $this->domain),
                '7036' => __('The period between the initial and second transaction must be greater than 24 hours', $this->domain),
                '7040' => __('The scheduleId is not valid or does not match to the connector', $this->domain),
                '7050' => __('The startDateTime is invalid or older than 24 hours', $this->domain),
                '7060' => __('The continueDateTime is invalid or older than 24 hours', $this->domain),
                '7070' => __('The status of the schedule is not valid for the requested operation', $this->domain)
            );

            return isset($errors[$code]) ? $errors[$code] : $unknownError;
        }
    }
    
    require_once ALLSECUREEXCHANGE_PLUGIN_PATH.'allsecure-exchange-additional-payment-method-abstract.php';
    /* Include additional payment method handler below */
    require_once ALLSECUREEXCHANGE_PLUGIN_PATH.'allsecure-exchange-sofort.php';
}

add_filter('woocommerce_payment_gateways', 'add_allsecureexchange_gateway_class');
function add_allsecureexchange_gateway_class($methods) {
    $methods[] = 'WC_AllsecureExchange';
    /* Add additional payment method to woocommerce below */
    $methods[] = 'AllsecureExchange_sofort';
    
    return $methods;
}
