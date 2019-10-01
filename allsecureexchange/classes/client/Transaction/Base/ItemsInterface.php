<?php

namespace AllsecureExchange\Client\Transaction\Base;
use AllsecureExchange\Client\Data\Item;

/**
 * Interface ItemsInterface
 *
 * @package AllsecureExchange\Client\Transaction\Base
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
