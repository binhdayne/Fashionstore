<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CopyDeliveryFeeToOrderObserver implements ObserverInterface
{
    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getQuote();
        $order = $observer->getEvent()->getOrder();

        if (!$quote || !$order) {
            return;
        }

        $deliveryMethod = (string) $quote->getData('fs_delivery_method');
        $deliveryFee = (float) $quote->getData('fs_delivery_fee');

        $order->setData('fs_delivery_method', $deliveryMethod);
        $order->setData('fs_delivery_fee', $deliveryFee);
    }
}
