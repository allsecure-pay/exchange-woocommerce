<?php
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

abstract class AllsecureExchange_Additional_Payment_Method_Abstract extends WC_Payment_Gateway {

    public $domain;
    public $method;
    public $title_default;
    public $allsecureMain;

    /**
     * Constructor for the gateway.
     */
    public function __construct() {
        $this->domain = 'allsecureexchange';
        $this->allsecureMain = new WC_AllsecureExchange(true);

        $this->supports = array(
            'products',
            'refunds'
        );
        
        $this->icon = ALLSECUREEXCHANGE_PLUGIN_URL . 'assets/images/logo.png';
        if (file_exists(ALLSECUREEXCHANGE_PLUGIN_PATH. 'assets/images/'.$this->method.'.png')) {
            $this->icon = ALLSECUREEXCHANGE_PLUGIN_URL . 'assets/images/'.$this->method.'.png';
        } 

        // Define user set variables
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        $this->api_key = $this->get_option('api_key');
        $this->shared_secret = $this->get_option('shared_secret');

        $this->success_order_status = 'processing';
        $this->has_fields = false;
        
        $settings = get_option('woocommerce_allsecureexchange_settings');
        
        $this->operation_mode = $settings['operation_mode'];
        $this->api_user = $settings['api_user'];
        $this->api_password = $settings['api_password'];
        $this->payment_action = $settings['payment_action'];
        $this->transaction_email = $settings['transaction_email'];
        $this->transaction_confirmation_page = $settings['transaction_confirmation_page'];
        $this->debug = $settings['debug'];

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_'. strtolower("AllsecureExchange_".$this->method), array( $this, 'check_api_response' ) );
        add_filter('woocommerce_thankyou_order_received_text', array($this, 'thankyou_page'), 10, 2);
        add_action('woocommerce_email_after_order_table', array($this,'email_after_order_table'), 10, 1);
        add_action('woocommerce_admin_order_totals_after_total', array($this, 'admin_order_totals'), 10, 2);
        add_action('woocommerce_order_item_add_action_buttons', array($this, 'admin_order_action_buttons'), 10, 1);
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
                'default' => $this->title_default,
                'desc_tip' => false,
            ),
            'description' => array(
                'title' => __('Description', $this->domain),
                'type' => 'textarea',
                'description' => __('This controls the descriptive text which the user sees while choosing this payment option.', $this->domain),
                'default' => '',
                'desc_tip' => false,
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
        );

        $this->form_fields = $field_arr;
    }

    /**
     * Process Gateway Settings Form Fields.
     */
    public function process_admin_options() {
        $this->init_settings();

        $post_data = $this->get_post_data();
        if (empty($post_data['woocommerce_'.$this->id.'_api_key'])) {
            WC_Admin_Settings::add_error(__('Please enter Allsecure Exchange API Key', $this->domain));
        } elseif (empty($post_data['woocommerce_'.$this->id.'_shared_secret'])) {
            WC_Admin_Settings::add_error(__('Please enter allsecureexchange API Shared Secret', $this->domain));
        } else {
            foreach ( $this->get_form_fields() as $key => $field ) {
                $setting_value = $this->get_field_value( $key, $field, $post_data );
                $this->settings[ $key ] = $setting_value;
            }

            return update_option( $this->get_option_key(), apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings ) );
         }
    }
    
    /**
     * Built in function to render the payment description and payment form fields
     *
     * @return void
     */
    function payment_fields() { 
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
        ?>
            
        <div class="form-row form-row-wide">
        <?php if (!empty($this->description)) {?>
        <p><?php echo $this->description; ?></p>
        <?php } ?>
        
     <?php    
    }

    /**
     * Process the payment and return the result.
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id) {
        global $woocommerce;

        $order = wc_get_order($order_id);

        try {
            $transaction_token = '';

            $action = $this->payment_action;

            if ($action == 'debit') {
                $result = $this->debitTransaction($order, $transaction_token);
            } else {
                $result = $this->preauthorizeTransaction($order, $transaction_token);
            }
            
            //print_r($result);

            // handle the result
            if ($result->isSuccess()) {
                $gatewayReferenceId = $result->getUuid();

                $order->add_meta_data($this->prefix.'transaction_id', $gatewayReferenceId, true);
                $order->add_meta_data($this->prefix.'payment_request_uuid', $gatewayReferenceId, true);
                $order->add_meta_data($this->prefix.'transaction_mode', $this->operation_mode, true);
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
                    $errorMessage = $this->allsecureMain->getErrorMessageByCode($errorCode);
                    throw new \Exception($errorMessage);
                } elseif ($result->getReturnType() == AllsecureResult::RETURN_TYPE_REDIRECT) {
                    //redirect the user
                    $order->add_meta_data($this->prefix.'status', 'redirected', true);
                    $redirectLink = $result->getRedirectUrl();
                    return [
                        'result' => 'success',
                        'redirect' => $redirectLink,
                    ];
                } elseif ($result->getReturnType() == AllsecureResult::RETURN_TYPE_PENDING) {
                    //payment is pending, wait for callback to complete
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
                        $order->add_meta_data($this->prefix.'status', 'debited', true);
                        $order->save_meta_data();
                        $comment1 = __('Allsecure Exchange payment is successfully debited. ', $this->domain);
                        $comment2 = __('Transaction ID', $this->domain).': ' .$gatewayReferenceId;
                        $comment = $comment1.$comment2;

                        $order_status = $this->allsecureMain->getOrderStatusBySlug($this->success_order_status);

                        $order->update_status($order_status, $comment);
                        $woocommerce->cart->empty_cart();

                        return [
                            'result' => 'success',
                            'redirect' => add_query_arg( 'order_id', $order_id, $this->get_return_url( $order ))
                        ];
                    } else {
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
                if ($errorCode == '9999') {
                    $errorMessage = $error->getMessage();
                } else {
                    $errorMessage = $this->allsecureMain->getErrorMessageByCode($errorCode);
                }
                throw new \Exception($errorMessage);
            }
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $this->allsecureMain->log('Payment Create Catch: '.$errorMessage);
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
            $this->allsecureMain->log('Return URL Called');
            $order_id = false;
            if (isset($_GET['order_id'])) {
                $order_id = (int)sanitize_text_field($_GET['order_id']);
            }

            if ($order_id) {
                $woocommerce->cart->empty_cart();
                wp_redirect(add_query_arg( 'order_id', $order_id, $this->get_return_url( $order )));exit;
            } else {
                throw new \Exception(__('No order data received', $this->domain));
            }
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $this->allsecureMain->log('Return URL Catch: '.$errorMessage);
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
            $this->allsecureMain->log('Error URL Called');
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
                    $errorMessage = $this->allsecureMain->getErrorMessageByCode($errorCode);

                    throw new \Exception($errorMessage);
                } else {
                    throw new \Exception(__('Error from gateway.', $this->domain));
                }
            } else {
                throw new \Exception(__('No order data received', $this->domain));
            }
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $this->allsecureMain->log('Error URL Catch: '.$errorMessage);
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
            $this->allsecureMain->log('Cancel URL Called');
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
            $this->allsecureMain->log('Cancel URL Catch: '.$errorMessage);
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
            $this->allsecureMain->log('Webhook URL Called');
            $client = $this->getClient();

            if (!$client->validateCallbackWithGlobals()) {
                throw new \Exception(__('Callback validation failed.', $this->domain));
            }

            $callbackResult = $client->readCallback(file_get_contents('php://input'));
            $this->allsecureMain->log((array)($callbackResult));
            $order_id = (int)sanitize_text_field($_GET['order_id']);
            if ($order_id > 0) {
                $order = new WC_Order($order_id);
                if ($order && $order->get_id() > 0) {
                    $merchantTransactionId = $callbackResult->getMerchantTransactionId();
                    $decodedOrderId = $this->allsecureMain->decodeOrderId($merchantTransactionId);
                    $this->allsecureMain->log('Concerning order:'.$order_id);

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
                            $order->add_meta_data($this->prefix.'status', 'debited');
                            $order->save_meta_data();

                            $comment1 = __('Allsecure Exchange payment is successfully debited. ', $this->domain);
                            $comment2 = __('Transaction ID', $this->domain).': ' .$gatewayReferenceId;
                            $comment = $comment1.$comment2;

                            $order_status = $this->allsecureMain->getOrderStatusBySlug($this->success_order_status);
                            $order->update_status($order_status, $comment);
                        } else if ($callbackResult->getTransactionType() == AllsecureCallbackResult::TYPE_CAPTURE) {
                            //result capture
                            $order->add_meta_data($this->prefix.'capture_uuid', $gatewayReferenceId);
                            $order->add_meta_data($this->prefix.'status', 'captured');
                            $order->save_meta_data();

                            $comment1 = __('Allsecure Exchange payment is successfully captured. ', $this->domain);
                            $comment2 = __('Transaction ID', $this->domain).': ' .$gatewayReferenceId;
                            $comment = $comment1.$comment2;

                            $order_status = $this->allsecureMain->getOrderStatusBySlug($this->success_order_status);
                            $order->update_status($order_status, $comment);
                        } else if ($callbackResult->getTransactionType() == AllsecureCallbackResult::TYPE_VOID) {
                            //result void
                            $order->add_meta_data($this->prefix.'void_uuid', $gatewayReferenceId);
                            $order->add_meta_data($this->prefix.'status', 'voided');
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
                            $order->add_meta_data($this->prefix.'status', 'preauthorized');
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
                        $order->add_meta_data($this->prefix.'status', 'error');
                        $order->save_meta_data();
                        $error = $callbackResult->getFirstError();
                        $errorCode = $error->getCode();
                        if (empty($errorCode)) {
                            $errorCode = $error->getAdapterCode();
                        }

                        $comment1 = __('Error from gateway.', $this->domain);
                        $comment2 = $this->allsecureMain->getErrorMessageByCode($errorCode);
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
            $this->allsecureMain->log('Webhook Catch: '.$e->getMessage());
            echo 'OK';
            exit;
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
                            $transactionId = $statusResult->getTransactionUuid() ?? NULL;
                            
                            if (strtolower($result) == 'pending') {
                                $comment1 = __('Allsecure Exchange payment request is created successfully and but payment status received as pending.', $this->domain);
                                $comment2 = __('Transaction ID', $this->domain).': ' .$transactionId;
                                $comment = $comment1.$comment2;

                                $order_status = 'wc-allsecurepending';
                                $order->update_status($order_status, $comment);
                            }
                            
                            $transactionType = $statusResult->getTransactionType();
                            $amount = $statusResult->getAmount();
                            $currency = $statusResult->getCurrency();
                            $cardData = $statusResult->getreturnData();
                            
                            $binBrand = '';
                            $lastFourDigits = '';
                            
                            if (method_exists($cardData, 'getType')) {
                                $binBrand = strtoupper($cardData->getType());
                            }
                            if (method_exists($cardData, 'getlastFourDigits')) {
                                $lastFourDigits = $cardData->getlastFourDigits();
                            }
                            
                            $bankName = '';
                            $accountOwner = '';
                            $iban = '';
                            
                            if (method_exists($cardData, 'getAccountOwner')) {
                                $accountOwner = strtoupper($cardData->getAccountOwner());
                            }
                            if (method_exists($cardData, 'getBankName')) {
                                $bankName = $cardData->getBankName();
                            }
                            if (method_exists($cardData, 'getIban')) {
                                $iban = $cardData->getIban();
                            }

                            $extraData = $statusResult->getextraData();

                            if ( isset($extraData['authCode']) ) {
                                $authCode = $extraData['authCode'];
                            } elseif (isset($extraData['adapterReferenceId']) ) {
                                $authCode = $extraData['adapterReferenceId'];
                            } else {
                                $authCode = NULL;
                            }
                            $timestamp = date("Y-m-d H:i:s");

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
                                            <?php echo __('Transaction Status', $this->domain)?>: <strong><?php echo $result?></strong>
                                        </li>
                                        <li class='woocommerce-order-overview__email email'>
                                            <?php echo __('Transaction ID', $this->domain)?>: <strong><?php echo $transactionId?></strong>
                                        </li>
                                        <?php if (!empty($lastFourDigits)) {?>
                                        <li class='woocommerce-order-overview__email email'>
                                            <?php echo __('Card Type', $this->domain)?>: <strong><?php echo $binBrand ." *** ".$lastFourDigits?></strong>
                                        </li>
                                        <?php } ?>
                                        <?php if (!empty($bankName)) {?>
                                        <li class='woocommerce-order-overview__email email'>
                                            <?php echo __('Bank Name', $this->domain)?>: <strong><?php echo $bankName?></strong>
                                        </li>
                                        <?php } ?>
                                        <?php if (!empty($accountOwner)) {?>
                                        <li class='woocommerce-order-overview__email email'>
                                            <?php echo __('Account Owner', $this->domain)?>: <strong><?php echo $accountOwner?></strong>
                                        </li>
                                        <?php } ?>
                                        <?php if (!empty($iban)) {?>
                                        <li class='woocommerce-order-overview__email email'>
                                            <?php echo __('IBAN/Account Number', $this->domain)?>: <strong><?php echo $iban?></strong>
                                        </li>
                                        <?php } ?>
                                        
                                        <li class='woocommerce-order-overview__email email'>
                                            <?php echo __('Payment Type', $this->domain)?>: <strong><?php echo $transactionType?></strong>
                                        </li>
                                        <li class='woocommerce-order-overview__email email'>
                                            <?php echo __('Currency', $this->domain)?>: <strong><?php echo $currency?></strong>
                                        </li>
                                        <li class='woocommerce-order-overview__email email'>
                                            <?php echo __('Amount Paid', $this->domain)?>: <strong><?php echo $amount?></strong>
                                        </li>
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
                        
                        $binBrand = '';
                        $lastFourDigits = '';

                        if (method_exists($cardData, 'getType')) {
                            $binBrand = strtoupper($cardData->getType());
                        }
                        if (method_exists($cardData, 'getlastFourDigits')) {
                            $lastFourDigits = $cardData->getlastFourDigits();
                        }

                        $bankName = '';
                        $accountOwner = '';
                        $iban = '';

                        if (method_exists($cardData, 'getAccountOwner')) {
                            $accountOwner = strtoupper($cardData->getAccountOwner());
                        }
                        if (method_exists($cardData, 'getBankName')) {
                            $bankName = $cardData->getBankName();
                        }
                        if (method_exists($cardData, 'getIban')) {
                            $iban = $cardData->getIban();
                        }
                        
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
                                    <?php if (!empty($lastFourDigits)) {?>
                                    <li class='woocommerce-order-overview__email email'>
                                        <?php echo __('Card Type', $this->domain)?>: <strong><?php echo $binBrand ." *** ".$lastFourDigits?></strong>
                                    </li>
                                    <?php } ?>
                                    <?php if (!empty($bankName)) {?>
                                    <li class='woocommerce-order-overview__email email'>
                                        <?php echo __('Bank Name', $this->domain)?>: <strong><?php echo $bankName?></strong>
                                    </li>
                                    <?php } ?>
                                    <?php if (!empty($accountOwner)) {?>
                                    <li class='woocommerce-order-overview__email email'>
                                        <?php echo __('Account Owner', $this->domain)?>: <strong><?php echo $accountOwner?></strong>
                                    </li>
                                    <?php } ?>
                                    <?php if (!empty($iban)) {?>
                                    <li class='woocommerce-order-overview__email email'>
                                        <?php echo __('IBAN/Account Number', $this->domain)?>: <strong><?php echo $iban?></strong>
                                    </li>
                                    <?php } ?>
                                    <li class='woocommerce-order-overview__email email'>
                                        <?php echo __('Payment Type', $this->domain)?>: <strong><?php echo $transactionType?></strong>
                                    </li>
                                    <li class='woocommerce-order-overview__email email'>
                                        <?php echo __('Currency', $this->domain)?>: <strong><?php echo $currency?></strong>
                                    </li>
                                    <li class='woocommerce-order-overview__email email'>
                                        <?php echo __('Amount Paid', $this->domain)?>: <strong><?php echo $amount?></strong>
                                    </li>
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

            $status = $order->get_meta($this->prefix.'status');
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
            $this->allsecureMain->log('Refund triggered');

            if ($amount <= 0 ) {
                throw new Exception(__('Refund amount shoule be greater than 0.',  $this->domain));
            }

            if ($amount < $order_total_paid) {
                throw new Exception(__('Amount shoule be equal to order paid total.',  $this->domain));
            }

            $transaction_id = $order->get_meta($this->prefix.'transaction_id');
            $merchantTransactionId = 'refund-'.$this->allsecureMain->encodeOrderId($order_id);

            $client = $this->getClient();

            $refund = new AllsecureRefund();
            $refund->setMerchantTransactionId($merchantTransactionId)
                    ->setAmount($amount)
                    ->setCurrency($order_data['currency'])
                    ->setReferenceUuid($transaction_id);

            $this->allsecureMain->log('Refund request');
            $this->allsecureMain->log((array)($refund));
            $result = $client->refund($refund);
            $this->allsecureMain->log('Refund response');
            $this->allsecureMain->log((array)($result));

            if ($result->getReturnType() == AllsecureResult::RETURN_TYPE_FINISHED) {
                $gatewayReferenceId = $result->getUuid();
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
                $errorMessage = $this->allsecureMain->getErrorMessageByCode($errorCode);
                throw new \Exception($errorMessage);
            } else {
                throw new \Exception(__('Unknown error', $this->domain));
            }
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $this->allsecureMain->log('Refund Catch: '.$message);
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
            $this->allsecureMain->log('Capture triggered');
            $order_id = false;
            if (isset($_GET['order_id'])) {
                $order_id = (int)sanitize_text_field($_GET['order_id']);
            }

            if ($order_id) {
                $order = new WC_Order($order_id);

                $order_data = $order->get_data();
                $order_total_paid = $order->get_total();

                $transaction_id = $order->get_meta($this->prefix.'transaction_id');
                $merchantTransactionId = 'capture-'.$this->allsecureMain->encodeOrderId($order_id);

                $client = $this->getClient();

                $capture = new AllsecureCapture();
                $capture->setTransactionId($merchantTransactionId)
                    ->setAmount($order_total_paid)
                    ->setCurrency($order_data['currency'])
                    ->setReferenceTransactionId($transaction_id);

                $this->allsecureMain->log('Capture request');
                $this->allsecureMain->log((array)($capture));
                $result = $client->Capture($capture);
                $this->allsecureMain->log('Capture response');
                $this->allsecureMain->log((array)($result));

                if ($result->getReturnType() == AllsecureResult::RETURN_TYPE_FINISHED) {
                    $gatewayReferenceId = $result->getUuid();
                    $order->add_meta_data($this->prefix.'status', 'captured', true);
                    $order->add_meta_data($this->prefix.'transaction_id', $gatewayReferenceId, true);
                    $order->add_meta_data($this->prefix.'capture_uuid', $gatewayReferenceId, true);
                    $order->save_meta_data();

                    $comment1 = __('Allsecure Exchange payment is successfully captured. ', $this->domain);
                    $comment2 = __('Transaction ID', $this->domain).': ' .$gatewayReferenceId;
                    $comment = $comment1.$comment2;

                    $order_status = $this->allsecureMain->getOrderStatusBySlug($this->success_order_status);
                    $order->update_status($order_status, $comment);

                    $status = 'success';
                    $message = $comment;
                } elseif ($result->getReturnType() == AllsecureResult::RETURN_TYPE_ERROR) {
                    $error = $result->getFirstError();
                    $errorCode = $error->getCode();
                    if (empty($errorCode)) {
                        $errorCode = $error->getAdapterCode();
                    }
                    $errorMessage = $this->allsecureMain->getErrorMessageByCode($errorCode);
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
            $this->allsecureMain->log('Capture Catch: '.$message);
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
            $this->allsecureMain->log('Void triggered');
            $order_id = false;
            if (isset($_GET['order_id'])) {
                $order_id = (int)sanitize_text_field($_GET['order_id']);
            }

            if ($order_id) {
                $order = new WC_Order($order_id);

                $transaction_id = $order->get_meta($this->prefix.'transaction_id');
                $merchantTransactionId = 'void-'.$this->allsecureMain->encodeOrderId($order_id);

                $client = $this->getClient();

                $void = new AllsecureVoidTransaction();
                $void->setMerchantTransactionId($merchantTransactionId)
                     ->setReferenceUuid($transaction_id);

                $this->allsecureMain->log('Void request');
                $this->allsecureMain->log((array)($void));
                $result = $client->void($void);
                $this->allsecureMain->log('Void response');
                $this->allsecureMain->log((array)($void));

                if ($result->getReturnType() == AllsecureResult::RETURN_TYPE_FINISHED) {
                    $gatewayReferenceId = $result->getUuid();
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
                    $errorMessage = $this->allsecureMain->getErrorMessageByCode($errorCode);
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
            $this->allsecureMain->log('Void Catch: '.$message);
        }

        $data = [
            'status' => $status,
            'message' => $message
        ];

        echo json_encode($data);
        die();
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
                'wc-api' => $this->id,
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
           AllsecureClient::setApiUrl($this->allsecureMain->getTestApiUrl());
       } else {
           AllsecureClient::setApiUrl($this->allsecureMain->getLiveApiUrl());
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
    * Process Transaction
    *
    * @param $order
    * @param string $token
    * @param string $action
    * 
    * @return $this
    */
   public function processTransaction($order, $token, $action)
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
       $merchantTransactionId = $this->allsecureMain->encodeOrderId($order_id);

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

       if ($action == 'debit') {
           $this->allsecureMain->log('Debit Transaction');
           $this->allsecureMain->log((array)($transasction));
           $result = $client->debit($transasction);
       } else {
           $this->allsecureMain->log('PreAuthorize Transaction');
           $this->allsecureMain->log((array)($transasction));
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
   public function debitTransaction($order, $token)
   {
       return @$this->processTransaction($order, $token, 'debit');
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
}
