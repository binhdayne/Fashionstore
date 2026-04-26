<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Model\Checkout;

use Magento\Quote\Model\Quote;

class DeliveryFeeManager
{
    private const DELIVERY_METHOD_STANDARD = 'standard';
    private const DELIVERY_METHOD_EXPRESS = 'express';
    private const EXPRESS_FEE = 30000.0;

    public function normalizeMethod(?string $deliveryMethod): string
    {
        return $deliveryMethod === self::DELIVERY_METHOD_EXPRESS
            ? self::DELIVERY_METHOD_EXPRESS
            : self::DELIVERY_METHOD_STANDARD;
    }

    public function getFeeByMethod(string $deliveryMethod): float
    {
        return $this->normalizeMethod($deliveryMethod) === self::DELIVERY_METHOD_EXPRESS
            ? self::EXPRESS_FEE
            : 0.0;
    }

    public function applyToQuote(Quote $quote, ?string $deliveryMethod): float
    {
        $normalizedMethod = $this->normalizeMethod($deliveryMethod);
        $targetFee = $quote->isVirtual() ? 0.0 : $this->getFeeByMethod($normalizedMethod);
        $currentFee = (float) $quote->getData('fs_delivery_fee');
        $delta = $targetFee - $currentFee;

        $quote->setData('fs_delivery_method', $normalizedMethod);
        $quote->setData('fs_delivery_fee', $targetFee);

        if (abs($delta) < 0.0001) {
            return $targetFee;
        }

        $shippingAddress = $quote->getShippingAddress();
        if ($shippingAddress) {
            $shippingAddress->setData('shipping_amount', ((float) $shippingAddress->getData('shipping_amount')) + $delta);
            $shippingAddress->setData('base_shipping_amount', ((float) $shippingAddress->getData('base_shipping_amount')) + $delta);
            $shippingAddress->setData('shipping_incl_tax', ((float) $shippingAddress->getData('shipping_incl_tax')) + $delta);
            $shippingAddress->setData('base_shipping_incl_tax', ((float) $shippingAddress->getData('base_shipping_incl_tax')) + $delta);
            $shippingAddress->setData('grand_total', ((float) $shippingAddress->getData('grand_total')) + $delta);
            $shippingAddress->setData('base_grand_total', ((float) $shippingAddress->getData('base_grand_total')) + $delta);
        }

        $quote->setData('shipping_amount', ((float) $quote->getData('shipping_amount')) + $delta);
        $quote->setData('base_shipping_amount', ((float) $quote->getData('base_shipping_amount')) + $delta);
        $quote->setGrandTotal(((float) $quote->getGrandTotal()) + $delta);
        $quote->setBaseGrandTotal(((float) $quote->getBaseGrandTotal()) + $delta);

        return $targetFee;
    }
}
