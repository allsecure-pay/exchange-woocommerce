<?php

namespace AllSecureExchange\Client\Transaction;

use AllSecureExchange\Client\Transaction\Base\AbstractTransactionWithReference;
use AllSecureExchange\Client\Transaction\Base\AmountableInterface;
use AllSecureExchange\Client\Transaction\Base\AmountableTrait;
use AllSecureExchange\Client\Transaction\Base\ItemsInterface;
use AllSecureExchange\Client\Transaction\Base\ItemsTrait;

/**
 * Capture: Charge a previously preauthorized transaction.
 *
 * @package AllSecureExchange\Client\Transaction
 */
class Capture extends AbstractTransactionWithReference implements AmountableInterface, ItemsInterface {
    use AmountableTrait;
    use ItemsTrait;
}
