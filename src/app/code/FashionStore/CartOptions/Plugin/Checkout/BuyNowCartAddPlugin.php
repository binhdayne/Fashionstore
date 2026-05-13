<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Plugin\Checkout;

use Magento\Checkout\Controller\Cart\Add;
use Magento\Checkout\Model\Session as CheckoutSession;

class BuyNowCartAddPlugin
{
    private const SESSION_KEY_ACTIVE = 'fashionstore_buy_now_active';
    private const SESSION_KEY_ITEM_IDS = 'fashionstore_buy_now_item_ids';
    private const REQUEST_PARAM = 'fashionstore_buy_now';

    private CheckoutSession $checkoutSession;

    public function __construct(CheckoutSession $checkoutSession)
    {
        $this->checkoutSession = $checkoutSession;
    }

    public function aroundExecute(Add $subject, callable $proceed)
    {
        $isBuyNow = (bool) $subject->getRequest()->getParam(self::REQUEST_PARAM, false);
        $beforeSnapshot = $isBuyNow ? $this->captureQuoteSnapshot() : [];

        $result = $proceed();

        if (!$isBuyNow) {
            $this->clearBuyNowState();

            return $result;
        }

        $targetItemIds = $this->resolveBuyNowItemIds($beforeSnapshot);

        if ($targetItemIds === []) {
            $this->clearBuyNowState();

            return $result;
        }

        $this->checkoutSession->setData(self::SESSION_KEY_ACTIVE, true);
        $this->checkoutSession->setData(self::SESSION_KEY_ITEM_IDS, $targetItemIds);

        return $result;
    }

    /**
     * @return array<int, float>
     */
    private function captureQuoteSnapshot(): array
    {
        $snapshot = [];

        foreach ($this->checkoutSession->getQuote()->getAllItems() as $item) {
            if (!$item->getId()) {
                continue;
            }

            $snapshot[(int) $item->getId()] = (float) $item->getQty();
        }

        return $snapshot;
    }

    /**
     * @param array<int, float> $beforeSnapshot
     * @return array<int>
     */
    private function resolveBuyNowItemIds(array $beforeSnapshot): array
    {
        $itemIds = [];

        foreach ($this->checkoutSession->getQuote()->getAllItems() as $item) {
            if (!$item->getId()) {
                continue;
            }

            $itemId = (int) $item->getId();
            $previousQty = $beforeSnapshot[$itemId] ?? null;

            if ($previousQty === null || (float) $item->getQty() > $previousQty) {
                $itemIds[] = $itemId;
            }
        }

        return array_values(array_unique($itemIds));
    }

    private function clearBuyNowState(): void
    {
        $this->checkoutSession->unsetData(self::SESSION_KEY_ACTIVE);
        $this->checkoutSession->unsetData(self::SESSION_KEY_ITEM_IDS);
    }
}