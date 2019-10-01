<?php

namespace AllsecureExchange\Client\Transaction;

use AllsecureExchange\Client\Transaction\Base\AbstractTransaction;
use AllsecureExchange\Client\Transaction\Base\AddToCustomerProfileInterface;
use AllsecureExchange\Client\Transaction\Base\AddToCustomerProfileTrait;
use AllsecureExchange\Client\Transaction\Base\OffsiteInterface;
use AllsecureExchange\Client\Transaction\Base\OffsiteTrait;
use AllsecureExchange\Client\Transaction\Base\ScheduleInterface;
use AllsecureExchange\Client\Transaction\Base\ScheduleTrait;

/**
 * Register: Register the customer's payment data for recurring charges.
 *
 * The registered customer payment data will be available for recurring transaction without user interaction.
 *
 * @package AllsecureExchange\Client\Transaction
 */
class Register extends AbstractTransaction implements OffsiteInterface, ScheduleInterface, AddToCustomerProfileInterface {
    use OffsiteTrait;
    use ScheduleTrait;
    use AddToCustomerProfileTrait;
}
