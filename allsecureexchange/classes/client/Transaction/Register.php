<?php

namespace AllSecureExchange\Client\Transaction;

use AllSecureExchange\Client\Transaction\Base\AbstractTransaction;
use AllSecureExchange\Client\Transaction\Base\AddToCustomerProfileInterface;
use AllSecureExchange\Client\Transaction\Base\AddToCustomerProfileTrait;
use AllSecureExchange\Client\Transaction\Base\OffsiteInterface;
use AllSecureExchange\Client\Transaction\Base\OffsiteTrait;
use AllSecureExchange\Client\Transaction\Base\ScheduleInterface;
use AllSecureExchange\Client\Transaction\Base\ScheduleTrait;

/**
 * Register: Register the customer's payment data for recurring charges.
 *
 * The registered customer payment data will be available for recurring transaction without user interaction.
 *
 * @package AllSecureExchange\Client\Transaction
 */
class Register extends AbstractTransaction implements OffsiteInterface, ScheduleInterface, AddToCustomerProfileInterface {
    use OffsiteTrait;
    use ScheduleTrait;
    use AddToCustomerProfileTrait;
}
