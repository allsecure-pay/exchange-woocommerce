<?php

class WC_AllSecureExchange_CreditCard extends WC_Payment_Gateway
{
    public $id = 'creditcard';

    public $method_title = 'Credit Card';

    /**
     * @var false|WP_User
     */
    protected $user;

    /**
     * @var WC_Order
     */
    protected $order;

    /**
     * @var string
     */
    protected $callbackUrl;

    public function __construct()
    {
        $this->id = ALL_SECURE_EXCHANGE_EXTENSION_UID_PREFIX . $this->id;
        $this->method_description = ALL_SECURE_EXCHANGE_EXTENSION_NAME . ' ' . $this->method_title . ' payments.';

        $this->has_fields = false;

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->callbackUrl = str_replace('https:', 'https:', add_query_arg('wc-api', 'wc_' . $this->id, home_url('/')));

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('wp_enqueue_scripts', function () {
            wp_register_script('payment_js', ALL_SECURE_EXCHANGE_EXTENSION_URL . 'js/integrated/payment.min.js', [], null, false);
        }, 999);
        add_action('woocommerce_api_wc_' . $this->id, [$this, 'process_callback']);
        add_filter('script_loader_tag', function ($tag, $handle) {
            if ($handle !== 'payment_js') {
                return $tag;
            }
            return str_replace(' src', ' data-main="payment-js" src', $tag);
        }, 10, 2);
    }

    public function process_payment($orderId)
    {
        global $woocommerce;

        $this->order = new WC_Order($orderId);
        $this->order->update_status('pending', __('Awaiting cheque payment', 'woocommerce'));

        $this->user = $this->order->get_user();

        WC_AllSecureExchange_Provider::autoloadClient();

        AllSecureExchange\Client\Client::setApiUrl($this->get_option('apiHost'));

        $client = new AllSecureExchange\Client\Client(
            $this->get_option('apiUser'),
            $this->get_option('apiPassword'),
            $this->get_option('apiKey'),
            $this->get_option('sharedSecret')
        );

        $customer = new AllSecureExchange\Client\Data\Customer();
        $customer
            ->setBillingAddress1($this->order->get_billing_address_1())
            ->setBillingAddress2($this->order->get_billing_address_2())
            ->setBillingCity($this->order->get_billing_city())
            ->setBillingCountry($this->order->get_billing_country())
            ->setBillingPhone($this->order->get_billing_phone())
            ->setBillingPostcode($this->order->get_billing_postcode())
            ->setBillingState($this->order->get_billing_state())
            ->setCompany($this->order->get_billing_company())
            ->setEmail($this->order->get_billing_email())
            ->setFirstName($this->order->get_billing_first_name())
            ->setIpAddress(WC_Geolocation::get_ip_address()) // $this->order->get_customer_ip_address()
            ->setLastName($this->order->get_billing_last_name());

        /**
         * add shipping data for non-digital goods
         */
        if ($this->order->get_shipping_country()) {
            $customer
                ->setShippingAddress1($this->order->get_shipping_address_1())
                ->setShippingAddress2($this->order->get_shipping_address_2())
                ->setShippingCity($this->order->get_shipping_city())
                ->setShippingCompany($this->order->get_shipping_company())
                ->setShippingCountry($this->order->get_shipping_country())
                ->setShippingFirstName($this->order->get_shipping_first_name())
                ->setShippingLastName($this->order->get_shipping_last_name())
                ->setShippingPostcode($this->order->get_shipping_postcode())
                ->setShippingState($this->order->get_shipping_state());
        }

        $debit = new \AllSecureExchange\Client\Transaction\Debit();
        $debit->setCustomer($customer)
            ->setExtraData($this->extraData3DS($this->order))
            ->setCallbackUrl($this->callbackUrl)
            ->setTransactionId($orderId)
            ->setAmount(floatval($this->order->get_total()))
            ->setCurrency($this->order->get_currency())
            ->setCancelUrl(wc_get_checkout_url())
            ->setSuccessUrl($this->get_return_url($this->order))
            ->setErrorUrl($this->get_return_url($this->order));

        $redirect = $this->get_return_url($this->order);
        $result = $client->debit($debit);
        if ($result->isSuccess()) {
            $woocommerce->cart->empty_cart();

            if ($result->getReturnType() == \AllSecureExchange\Client\Transaction\Result::RETURN_TYPE_REDIRECT) {
                $redirect = $result->getRedirectUrl();
            }
            return [
                'result' => 'success',
                'redirect' => $redirect,
            ];
        }

        /**
         * something went wrong
         */
        wp_send_json_error([
            'error' => $result->getFirstError()->getMessage(),
        ]);
    }

    public function process_callback()
    {
        WC_AllSecureExchange_Provider::autoloadClient();

        AllSecureExchange\Client\Client::setApiUrl($this->get_option('apiHost'));
        $client = new AllSecureExchange\Client\Client(
            $this->get_option('apiUser'),
            $this->get_option('apiPassword'),
            $this->get_option('apiKey'),
            $this->get_option('sharedSecret')
        );

        $client->validateCallbackWithGlobals();
        $callbackResult = $client->readCallback(file_get_contents('php://input'));
        $this->order = new WC_Order($callbackResult->getTransactionId());
        if ($callbackResult->getResult() == \AllSecureExchange\Client\Callback\Result::RESULT_OK) {
            $this->order->payment_complete();
        }

        die("OK");
    }

    public function init_form_fields()
    {
        $this->form_fields = [
            'title' => [
                'title' => 'Title',
                'type' => 'text',
                'label' => 'Title',
                'description' => 'Title',
                'default' => $this->method_title,
            ],
            'apiHost' => [
                'title' => 'API Host',
                'type' => 'text',
                'label' => 'API Host',
                'description' => 'API Host',
                'default' => ALL_SECURE_EXCHANGE_EXTENSION_URL,
            ],
            'apiUser' => [
                'title' => 'API User',
                'type' => 'text',
                'label' => 'API User',
                'description' => 'API User',
                'default' => '',
            ],
            'apiPassword' => [
                'title' => 'API Password',
                'type' => 'text',
                'label' => 'API Password',
                'description' => 'API Password',
                'default' => '',
            ],
            'apiKey' => [
                'title' => 'API Key',
                'type' => 'text',
                'label' => 'API Key',
                'description' => 'API Key',
                'default' => '',
            ],
            'sharedSecret' => [
                'title' => 'Shared Secret',
                'type' => 'text',
                'label' => 'Shared Secret',
                'description' => 'Shared Secret',
                'default' => '',
            ],
        ];
    }

    public function payment_fields()
    {
        wp_enqueue_script('payment_js');
        echo '<div>
                    <label for="card_holder">Card holder</label>
                    <input type="text" id="card_holder" name="card_holder" />
                </div>
                <div>
                    <label for="number_div">Card number</label>
                    <div id="number_div" style="height: 35px; width: 200px;"></div>
                </div>
                <div>
                    <label for="cvv_div">CVV</label>
                    <div id="cvv_div" style="height: 35px; width: 200px;"></div>
                </div>
            
                <div>
                    <label for="exp_month">Month</label>
                    <input type="text" id="exp_month" name="exp_month" />
                </div>
                <div>
                    <label for="exp_year">Year</label>
                    <input type="text" id="exp_year" name="exp_year" />
                </div>';
    }

    /**
     * @throws Exception
     * @return array
     */
    private function extraData3DS()
    {
        $extraData = [
            /**
             * Browser 3ds data injected by payment.js
             */
            // 3ds:browserAcceptHeader
            // 3ds:browserIpAddress
            // 3ds:browserJavaEnabled
            // 3ds:browserLanguage
            // 3ds:browserColorDepth
            // 3ds:browserScreenHeight
            // 3ds:browserScreenWidth
            // 3ds:browserTimezone
            // 3ds:browserUserAgent

            /**
             * Additional 3ds 2.0 data
             */
            '3ds:addCardAttemptsDay' => $this->addCardAttemptsDay(),
            '3ds:authenticationIndicator' => $this->authenticationIndicator(),
            '3ds:billingAddressLine3' => $this->billingAddressLine3(),
            '3ds:billingShippingAddressMatch' => $this->billingShippingAddressMatch(),
            '3ds:browserChallengeWindowSize' => $this->browserChallengeWindowSize(),
            '3ds:cardholderAccountAgeIndicator' => $this->cardholderAccountAgeIndicator(),
            '3ds:cardHolderAccountChangeIndicator' => $this->cardHolderAccountChangeIndicator(),
            '3ds:cardholderAccountDate' => $this->cardholderAccountDate(),
            '3ds:cardholderAccountLastChange' => $this->cardholderAccountLastChange(),
            '3ds:cardholderAccountLastPasswordChange' => $this->cardholderAccountLastPasswordChange(),
            '3ds:cardholderAccountPasswordChangeIndicator' => $this->cardholderAccountPasswordChangeIndicator(),
            '3ds:cardholderAccountType' => $this->cardholderAccountType(),
            '3ds:cardHolderAuthenticationData' => $this->cardHolderAuthenticationData(),
            '3ds:cardholderAuthenticationDateTime' => $this->cardholderAuthenticationDateTime(),
            '3ds:cardholderAuthenticationMethod' => $this->cardholderAuthenticationMethod(),
            '3ds:challengeIndicator' => $this->challengeIndicator(),
            '3ds:channel' => $this->channel(),
            '3ds:deliveryEmailAddress' => $this->deliveryEmailAddress(),
            '3ds:deliveryTimeframe' => $this->deliveryTimeframe(),
            '3ds:giftCardAmount' => $this->giftCardAmount(),
            '3ds:giftCardCount' => $this->giftCardCount(),
            '3ds:giftCardCurrency' => $this->giftCardCurrency(),
            '3ds:homePhoneCountryPrefix' => $this->homePhoneCountryPrefix(),
            '3ds:homePhoneNumber' => $this->homePhoneNumber(),
            '3ds:mobilePhoneCountryPrefix' => $this->mobilePhoneCountryPrefix(),
            '3ds:mobilePhoneNumber' => $this->mobilePhoneNumber(),
            '3ds:paymentAccountAgeDate' => $this->paymentAccountAgeDate(),
            '3ds:paymentAccountAgeIndicator' => $this->paymentAccountAgeIndicator(),
            '3ds:preOrderDate' => $this->preOrderDate(),
            '3ds:preOrderPurchaseIndicator' => $this->preOrderPurchaseIndicator(),
            '3ds:priorAuthenticationData' => $this->priorAuthenticationData(),
            '3ds:priorAuthenticationDateTime' => $this->priorAuthenticationDateTime(),
            '3ds:priorAuthenticationMethod' => $this->priorAuthenticationMethod(),
            '3ds:priorReference' => $this->priorReference(),
            '3ds:purchaseCountSixMonths' => $this->purchaseCountSixMonths(),
            '3ds:purchaseDate' => $this->purchaseDate(),
            '3ds:purchaseInstalData' => $this->purchaseInstalData(),
            '3ds:recurringExpiry' => $this->recurringExpiry(),
            '3ds:recurringFrequency' => $this->recurringFrequency(),
            '3ds:reorderItemsIndicator' => $this->reorderItemsIndicator(),
            '3ds:shipIndicator' => $this->shipIndicator(),
            '3ds:shippingAddressFirstUsage' => $this->shippingAddressFirstUsage(),
            '3ds:shippingAddressLine3' => $this->shippingAddressLine3(),
            '3ds:shippingAddressUsageIndicator' => $this->shippingAddressUsageIndicator(),
            '3ds:shippingNameEqualIndicator' => $this->shippingNameEqualIndicator(),
            '3ds:suspiciousAccountActivityIndicator' => $this->suspiciousAccountActivityIndicator(),
            '3ds:transactionActivityDay' => $this->transactionActivityDay(),
            '3ds:transactionActivityYear' => $this->transactionActivityYear(),
            '3ds:transType' => $this->transType(),
            '3ds:workPhoneCountryPrefix' => $this->workPhoneCountryPrefix(),
            '3ds:workPhoneNumber' => $this->workPhoneNumber(),
        ];

        return array_filter($extraData, function ($data) {
            return $data !== null;
        });
    }

    /**
     * 3ds:addCardAttemptsDay
     * Number of Add Card attempts in the last 24 hours.
     *
     * @return int|null
     */
    private function addCardAttemptsDay()
    {
        return null;
    }

    /**
     * 3ds:authenticationIndicator
     * Indicates the type of Authentication request. This data element provides additional information to the ACS to determine the best approach for handling an authentication request.
     * 01 -> Payment transaction
     * 02 -> Recurring transaction
     * 03 -> Installment transaction
     * 04 -> Add card
     * 05 -> Maintain card
     * 06 -> Cardholder verification as part of EMV token ID&V
     *
     * @return string|null
     */
    private function authenticationIndicator()
    {
        return null;
    }

    /**
     * 3ds:billingAddressLine3
     * Line 3 of customer's billing address
     *
     * @return string|null
     */
    private function billingAddressLine3()
    {
        return null;
    }

    /**
     * 3ds:billingShippingAddressMatch
     * Indicates whether the Cardholder Shipping Address and Cardholder Billing Address are the same.
     * Y -> Shipping Address matches Billing Address
     * N -> Shipping Address does not match Billing Address
     *
     * @return string|null
     */
    private function billingShippingAddressMatch()
    {
        return null;
    }

    /**
     * 3ds:browserChallengeWindowSize
     * Dimensions of the challenge window that has been displayed to the Cardholder. The ACS shall reply with content that is formatted to appropriately render in this window to provide the best possible user experience.
     * 01 -> 250 x 400
     * 02 -> 390 x 400
     * 03 -> 500 x 600
     * 04 -> 600 x 400
     * 05 -> Full screen
     *
     * @return string|null
     */
    private function browserChallengeWindowSize()
    {
        return '05';
    }

    /**
     * 3ds:cardholderAccountAgeIndicator
     * Length of time that the cardholder has had the account with the 3DS Requestor.
     * 01 -> No account (guest check-out)
     * 02 -> During this transaction
     * 03 -> Less than 30 days
     * 04 -> 30 - 60 days
     * 05 -> More than 60 days
     *
     * @return string|null
     */
    private function cardholderAccountAgeIndicator()
    {
        return null;
    }

    /**
     * 3ds:cardHolderAccountChangeIndicator
     * Length of time since the cardholder’s account information with the 3DS Requestor waslast changed. Includes Billing or Shipping address, new payment account, or new user(s) added.
     * 01 -> Changed during this transaction
     * 02 -> Less than 30 days
     * 03 -> 30 - 60 days
     * 04 -> More than 60 days
     *
     * @return string|null
     */
    private function cardHolderAccountChangeIndicator()
    {
        return null;
    }

    /**
     * Date that the cardholder opened the account with the 3DS Requestor. Format: YYYY-MM-DD
     * Example: 2019-05-12
     *
     * @throws Exception
     * @return string|null
     */
    private function cardholderAccountDate()
    {
        if (!$this->user) {
            return null;
        }

        return $this->user->user_registered ? (new DateTime($this->user->user_registered))->format('Y-m-d') : null;
    }

    /**
     * 3ds:cardholderAccountLastChange
     * Date that the cardholder’s account with the 3DS Requestor was last changed. Including Billing or Shipping address, new payment account, or new user(s) added. Format: YYYY-MM-DD
     * Example: 2019-05-12
     *
     * @throws Exception
     * @return string|null
     */
    private function cardholderAccountLastChange()
    {
        if (!$this->user) {
            return null;
        }

        $lastUpdate = get_user_meta($this->user->ID, 'last_update', true);

        return $lastUpdate ? (new DateTime('@' . $lastUpdate))->format('Y-m-d') : null;
    }

    /**
     * 3ds:cardholderAccountLastPasswordChange
     * Date that cardholder’s account with the 3DS Requestor had a password change or account reset. Format: YYYY-MM-DD
     * Example: 2019-05-12
     *
     * @return string|null
     */
    private function cardholderAccountLastPasswordChange()
    {
        return null;
    }

    /**
     * 3ds:cardholderAccountPasswordChangeIndicator
     * Length of time since the cardholder’s account with the 3DS Requestor had a password change or account reset.
     * 01 -> No change
     * 02 -> Changed during this transaction
     * 03 -> Less than 30 days
     * 04 -> 30 - 60 days
     * 05 -> More than 60 days
     *
     * @return string|null
     */
    private function cardholderAccountPasswordChangeIndicator()
    {
        return null;
    }

    /**
     * 3ds:cardholderAccountType
     * Indicates the type of account. For example, for a multi-account card product.
     * 01 -> Not applicable
     * 02 -> Credit
     * 03 -> Debit
     * 80 -> JCB specific value for Prepaid
     *
     * @return string|null
     */
    private function cardholderAccountType()
    {
        return null;
    }

    /**
     * 3ds:cardHolderAuthenticationData
     * Data that documents and supports a specific authentication process. In the current version of the specification, this data element is not defined in detail, however the intention is that for each 3DS Requestor Authentication Method, this field carry data that the ACS can use to verify the authentication process.
     *
     * @return string|null
     */
    private function cardHolderAuthenticationData()
    {
        return null;
    }

    /**
     * 3ds:cardholderAuthenticationDateTime
     * Date and time in UTC of the cardholder authentication. Format: YYYY-MM-DD HH:mm
     * Example: 2019-05-12 18:34
     *
     * @return string|null
     */
    private function cardholderAuthenticationDateTime()
    {
        return null;
    }

    /**
     * 3ds:cardholderAuthenticationMethod

    Mechanism used by the Cardholder to authenticate to the 3DS Requestor.
    01 -> No 3DS Requestor authentication occurred (i.e. cardholder "logged in" as guest)
    02 -> Login to the cardholder account at the 3DS Requestor system using 3DS Requestor's own credentials
    03 -> Login to the cardholder account at the 3DS Requestor system using federated ID
    04 -> Login to the cardholder account at the 3DS Requestor system using issuer credentials
    05 -> Login to the cardholder account at the 3DS Requestor system using third-party authentication
    06 -> Login to the cardholder account at the 3DS Requestor system using FIDO Authenticator
     *
     * @return string|null
     */
    private function cardholderAuthenticationMethod()
    {
        return null;
    }

    /**
     * 3ds:challengeIndicator

    Indicates whether a challenge is requested for this transaction. For example: For 01-PA, a 3DS Requestor may have concerns about the transaction, and request a challenge.
    01 -> No preference
    02 -> No challenge requested
    03 -> Challenge requested: 3DS Requestor Preference
    04 -> Challenge requested: Mandate

     *
     * @return string|null
     */
    private function challengeIndicator()
    {
        return null;
    }

    /**
     * 3ds:channel

    Indicates the type of channel interface being used to initiate the transaction
    01 -> App-based
    02 -> Browser
    03 -> 3DS Requestor Initiated

     *
     * @return string|null
     */
    private function channel()
    {
        return null;
    }

    /**
     * 3ds:deliveryEmailAddress

    For electronic delivery, the email address to which the merchandise was delivered.

     *
     * @return string|null
     */
    private function deliveryEmailAddress()
    {
        return null;
    }

    /**
     * 3ds:deliveryTimeframe

    Indicates the merchandise delivery timeframe.
    01 -> Electronic Delivery
    02 -> Same day shipping
    03 -> Overnight shipping
    04 -> Two-day or more shipping

     *
     * @return string|null
     */
    private function deliveryTimeframe()
    {
        return null;
    }

    /**
     * 3ds:giftCardAmount

    For prepaid or gift card purchase, the purchase amount total of prepaid or gift card(s) in major units (for example, USD 123.45 is 123).

     *
     * @return string|null
     */
    private function giftCardAmount()
    {
        return null;
    }

    /**
     * 3ds:giftCardCount

    For prepaid or gift card purchase, total count of individual prepaid or gift cards/codes purchased. Field is limited to 2 characters.

     *
     * @return string|null
     */
    private function giftCardCount()
    {
        return null;
    }

    /**
     * 3ds:giftCardCurrency

    For prepaid or gift card purchase, the currency code of the card

     *
     * @return string|null
     */
    private function giftCardCurrency()
    {
        return null;
    }

    /**
     * 3ds:homePhoneCountryPrefix

    Country Code of the home phone, limited to 1-3 characters

     *
     * @return string|null
     */
    private function homePhoneCountryPrefix()
    {
        return null;
    }

    /**
     * @return string|null
     */
    private function homePhoneNumber()
    {
        return null;
    }

    /**
     * @return string|null
     */
    private function mobilePhoneCountryPrefix()
    {
        return null;
    }

    /**
     * @return string|null
     */
    private function mobilePhoneNumber()
    {
        return null;
    }

    /**
     * @return string|null
     */
    private function paymentAccountAgeDate()
    {
        return null;
    }

    /**
     * @return string|null
     */
    private function paymentAccountAgeIndicator()
    {
        return null;
    }

    /**
     * @return string|null
     */
    private function preOrderDate()
    {
        return null;
    }

    /**
     * @return string|null
     */
    private function preOrderPurchaseIndicator()
    {
        return null;
    }

    /**
     * @return string|null
     */
    private function priorAuthenticationData()
    {
        return null;
    }

    /**
     * @return string|null
     */
    private function priorAuthenticationDateTime()
    {
        return null;
    }

    /**
     * @return string|null
     */
    private function priorAuthenticationMethod()
    {
        return null;
    }

    /**
     * @return string|null
     */
    private function priorReference()
    {
        return null;
    }

    /**
     * 3ds:purchaseCountSixMonths
     * Number of purchases with this cardholder account during the previous six months.
     *
     * @return int
     */
    private function purchaseCountSixMonths()
    {
        if (!$this->user) {
            return null;
        }

        $count = 0;
        foreach (['processing', 'completed', 'refunded', 'cancelled', 'authorization'] as $status) {
            $orders = wc_get_orders([
                'customer' => $this->user->ID,
                'limit' => -1,
                'status' => $status,
                'date_after' => '6 months ago',
            ]);
            $count += count($orders);
        }
        return $count;
    }

    /**
     * @return string|null
     */
    private function purchaseDate()
    {
        return null;
    }

    /**
     * @return string|null
     */
    private function purchaseInstalData()
    {
        return null;
    }

    /**
     * @return string|null
     */
    private function recurringExpiry()
    {
        return null;
    }

    /**
     * @return string|null
     */
    private function recurringFrequency()
    {
        return null;
    }

    /**
     * @return string|null
     */
    private function reorderItemsIndicator()
    {
        return null;
    }

    /**
     * @return string|null
     */
    private function shipIndicator()
    {
        return null;
    }

    /**
     * Date when the shipping address used for this transaction was first used with the 3DS Requestor. Format: YYYY-MM-DD
     * Example: 2019-05-12
     *
     * @throws Exception
     * @return string|null
     */
    private function shippingAddressFirstUsage()
    {
        if (!$this->user) {
            return null;
        }

        $orders = wc_get_orders([
            'customer' => $this->user->ID,
            'shipping_address_1' => $this->order->get_shipping_address_1(),
            'orderby' => 'date',
            'order' => 'ASC',
            'limit' => 1,
            'paginate' => false,
        ]);

        /** @var WC_Order $firstOrder */
        $firstOrder = reset($orders);
        $firstOrderDate = $firstOrder && $firstOrder->get_date_created() ? $firstOrder->get_date_created() : new WC_DateTime();
        return $firstOrderDate->format('Y-m-d');
    }

    /**
     * @return string|null
     */
    private function shippingAddressLine3()
    {
        return null;
    }

    /**
     * @return string|null
     */
    private function shippingAddressUsageIndicator()
    {
        return null;
    }

    /**
     * @return string|null
     */
    private function shippingNameEqualIndicator()
    {
        return null;
    }

    /**
     * @return string|null
     */
    private function suspiciousAccountActivityIndicator()
    {
        return null;
    }

    /**
     * @return string|null
     */
    private function transactionActivityDay()
    {
        return null;
    }

    /**
     * @return string|null
     */
    private function transactionActivityYear()
    {
        return null;
    }

    /**
     * @return string|null
     */
    private function transType()
    {
        return null;
    }

    /**
     * @return string|null
     */
    private function workPhoneCountryPrefix()
    {
        return null;
    }

    /**
     * @return string|null
     */
    private function workPhoneNumber()
    {
        return null;
    }
}
