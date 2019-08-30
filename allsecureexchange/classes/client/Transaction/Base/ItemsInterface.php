<?php

namespace AllSecureExchange\Client\Transaction\Base;
use AllSecureExchange\Client\Data\Item;

/**
 * Interface ItemsInterface
 *
 * @package AllSecureExchange\Client\Transaction\Base
 */
interface ItemsInterface {

    /**
     * @param Item[] $items
     * @return void
     */
    public function setItems($items);

    /**
     * @return Item[]
     */
    public function getItems();

    /**
     * @param Item $item
     * @return void
     */
    public function addItem($item);

}
