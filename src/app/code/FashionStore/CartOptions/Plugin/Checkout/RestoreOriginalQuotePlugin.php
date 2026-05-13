<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Plugin\Checkout;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;

class RestoreOriginalQuotePlugin
{
    private const SESSION_KEY_ACTIVE = 'fashionstore_buy_now_active';
    private const SESSION_KEY_ITEM_IDS = 'fashionstore_buy_now_item_ids';
    private const SESSION_KEY_ORIGINAL_QUOTE_ID = 'fashionstore_buy_now_original_quote_id';
    private const SESSION_KEY_QUOTE_ID = 'fashionstore_buy_now_quote_id';

    private CheckoutSession $checkoutSession;

    private CartRepositoryInterface $cartRepository;

    public function __construct(
        CheckoutSession $checkoutSession,
        CartRepositoryInterface $cartRepository
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->cartRepository = $cartRepository;
    }

    public function beforeExecute($subject): void
    {
        $buyNowQuoteId = (int) $this->checkoutSession->getData(self::SESSION_KEY_QUOTE_ID);
        $originalQuoteId = (int) $this->checkoutSession->getData(self::SESSION_KEY_ORIGINAL_QUOTE_ID);

        if ($buyNowQuoteId <= 0 || $originalQuoteId <= 0 || (int) $this->checkoutSession->getQuoteId() !== $buyNowQuoteId) {
            return;
        }

        try {
            $originalQuote = $this->cartRepository->get($originalQuoteId);
            $this->checkoutSession->replaceQuote($originalQuote);
        } catch (NoSuchEntityException $exception) {
        }

        $this->clearBuyNowState();
    }

    private function clearBuyNowState(): void
    {
        $this->checkoutSession->unsetData(self::SESSION_KEY_ACTIVE);
        $this->checkoutSession->unsetData(self::SESSION_KEY_ITEM_IDS);
        $this->checkoutSession->unsetData(self::SESSION_KEY_ORIGINAL_QUOTE_ID);
        $this->checkoutSession->unsetData(self::SESSION_KEY_QUOTE_ID);
    }
}