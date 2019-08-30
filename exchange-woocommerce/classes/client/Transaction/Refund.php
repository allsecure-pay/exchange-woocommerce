<?php

namespace AllSecureExchange\Client\Transaction;

use AllSecureExchange\Client\Transaction\Base\AbstractTransactionWithReference;
use AllSecureExchange\Client\Transaction\Base\AmountableInterface;
use AllSecureExchange\Client\Transaction\Base\AmountableTrait;
use AllSecureExchange\Client\Transaction\Base\ItemsInterface;
use AllSecureExchange\Client\Transaction\Base\ItemsTrait;

/**
 * Refund: Refund money from a previous Debit (or Capture) transaction to the customer.
 *
 * @note Preauthorized transactions can be reverted with a Void transaction, not a Refund!
 *
 * @package AllSecureExchange\Client\Transaction
 */
class Refund extends AbstractTransactionWithReference implements AmountableInterface, ItemsInterface {
    use AmountableTrait;
    use ItemsTrait;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var string
     */
    protected $callbackUrl;

    /**
     * @return string
     */
    public function getDescription() {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription($description) {
        $this->description = $description;
    }

    /**
     * @return string
     */
    public function getCallbackUrl() {
        return $this->callbackUrl;
    }

    /**
     * @param string $callbackUrl
     */
    public function setCallbackUrl($callbackUrl) {
        $this->callbackUrl = $callbackUrl;
    }
}
