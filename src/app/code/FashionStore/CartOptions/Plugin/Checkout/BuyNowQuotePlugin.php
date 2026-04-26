<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Plugin\Checkout;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;

class BuyNowQuotePlugin
{
    private const SESSION_KEY_ACTIVE = 'fashionstore_buy_now_active';
    private const SESSION_KEY_QUOTE_ID = 'fashionstore_buy_now_quote_id';

    private CustomerSession $customerSession;

    public function __construct(CustomerSession $customerSession)
    {
        $this->customerSession = $customerSession;
    }

    public function aroundLoadCustomerQuote(CheckoutSession $subject, callable $proceed): CheckoutSession
    {
        $result = $proceed();

        if (!$subject->getData(self::SESSION_KEY_ACTIVE)) {
            return $result;
        }

        if (!$this->customerSession->getCustomerId()) {
            return $result;
        }

        if ((int) $subject->getQuoteId() !== (int) $subject->getData(self::SESSION_KEY_QUOTE_ID)) {
            return $result;
        }

        $quote = $subject->getQuote();
        $quote->getBillingAddress();
        $quote->getShippingAddress();
        $quote->setCustomer($this->customerSession->getCustomerDataObject())
            ->setCustomerIsGuest(0)
            ->setTotalsCollectedFlag(false)
            ->collectTotals();

        $quote->save();
        $subject->setQuoteId($quote->getId());

        return $result;
    }
}