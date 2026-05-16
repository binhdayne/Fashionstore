<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Model\Checkout;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;

class DeliveryFeeManager
{
    public const DELIVERY_METHOD_GHTK = 'ghtk';
    public const DELIVERY_METHOD_GHN = 'ghn';
    private const LEGACY_DELIVERY_METHOD_STANDARD = 'standard';
    private const LEGACY_DELIVERY_METHOD_EXPRESS = 'express';
    private const SHIPPING_METHOD_GHTK = 'fashionstore_ghtk_standard';
    private const SHIPPING_METHOD_GHN = 'fashionstore_ghn_standard';
    private const SHIPPING_DESCRIPTION_GHTK = 'Giao Hang Tiet Kiem - GHTK - Giao tiet kiem';
    private const SHIPPING_DESCRIPTION_GHN = 'Giao Hang Nhanh - GHN - Giao nhanh';
    private const XML_PATH_GHTK_PRICE = 'carriers/fashionstore_ghtk/price';
    private const XML_PATH_GHN_PRICE = 'carriers/fashionstore_ghn/price';
    private const DEFAULT_GHTK_FEE = 20000.0;
    private const DEFAULT_GHN_FEE = 30000.0;

    private ScopeConfigInterface $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function normalizeMethod(?string $deliveryMethod): string
    {
        return in_array($deliveryMethod, [self::DELIVERY_METHOD_GHN, self::LEGACY_DELIVERY_METHOD_EXPRESS], true)
            ? self::DELIVERY_METHOD_GHN
            : self::DELIVERY_METHOD_GHTK;
    }

    public function getFeeByMethod(string $deliveryMethod): float
    {
        if ($this->normalizeMethod($deliveryMethod) === self::DELIVERY_METHOD_GHN) {
            return $this->getConfiguredFee(self::XML_PATH_GHN_PRICE, self::DEFAULT_GHN_FEE);
        }

        return $this->getConfiguredFee(self::XML_PATH_GHTK_PRICE, self::DEFAULT_GHTK_FEE);
    }

    public function applyToQuote(Quote $quote, ?string $deliveryMethod): float
    {
        $normalizedMethod = $this->normalizeMethod($deliveryMethod);
        $targetFee = $quote->isVirtual() ? 0.0 : $this->getFeeByMethod($normalizedMethod);
        $currentFee = (float) $quote->getData('fs_delivery_fee');
        $delta = $targetFee - $currentFee;

        $quote->setData('fs_delivery_method', $normalizedMethod);
        $quote->setData('fs_delivery_fee', $targetFee);

        $this->applyShippingMethod($quote, $normalizedMethod);

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

    private function applyShippingMethod(Quote $quote, string $deliveryMethod): void
    {
        $shippingAddress = $quote->getShippingAddress();
        if (!$shippingAddress) {
            return;
        }

        if ($deliveryMethod === self::DELIVERY_METHOD_GHN) {
            $shippingAddress->setShippingMethod(self::SHIPPING_METHOD_GHN);
            $shippingAddress->setShippingDescription(self::SHIPPING_DESCRIPTION_GHN);
            return;
        }

        $shippingAddress->setShippingMethod(self::SHIPPING_METHOD_GHTK);
        $shippingAddress->setShippingDescription(self::SHIPPING_DESCRIPTION_GHTK);
    }

    private function getConfiguredFee(string $path, float $defaultFee): float
    {
        $value = (float) $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE);

        return $value >= 0.0 ? $value : $defaultFee;
    }
}
