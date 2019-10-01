<?php

namespace AllsecureExchange\Client\Transaction;

use AllsecureExchange\Client\Transaction\Base\AbstractTransactionWithReference;
use AllsecureExchange\Client\Transaction\Base\AmountableInterface;
use AllsecureExchange\Client\Transaction\Base\AmountableTrait;
use AllsecureExchange\Client\Transaction\Base\ItemsInterface;
use AllsecureExchange\Client\Transaction\Base\ItemsTrait;

/**
 * Capture: Charge a previously preauthorized transaction.
 *
 * @package AllsecureExchange\Client\Transaction
 */
class Capture extends AbstractTransactionWithReference implements AmountableInterface, ItemsInterface {
    use AmountableTrait;
    use ItemsTrait;
}
