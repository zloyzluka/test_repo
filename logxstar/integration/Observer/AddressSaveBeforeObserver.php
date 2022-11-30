<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Logxstar\Integration\Observer;

use Magento\Framework\Event\ObserverInterface;

class AddressSaveBeforeObserver implements ObserverInterface
{

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $address = $observer->getData('data_object');
        $street = $address->getData('street');
        if (is_array($street)) {
            $street = implode('\n', $street);
            $address->setData('street', $street);
        }
    }
}
