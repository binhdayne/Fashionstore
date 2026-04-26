<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Controller\Cart;

use FashionStore\CartOptions\Model\Checkout\DeliveryFeeManager;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Quote\Api\CartRepositoryInterface;

class Setdeliverymethod extends Action
{
    private CheckoutSession $checkoutSession;

    private CartRepositoryInterface $cartRepository;

    private JsonFactory $resultJsonFactory;

    private DeliveryFeeManager $deliveryFeeManager;

    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        CartRepositoryInterface $cartRepository,
        JsonFactory $resultJsonFactory,
        DeliveryFeeManager $deliveryFeeManager
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->cartRepository = $cartRepository;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->deliveryFeeManager = $deliveryFeeManager;
    }

    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();

        try {
            $quote = $this->checkoutSession->getQuote();
            if (!$quote->getId()) {
                return $result->setData([
                    'success' => false,
                    'message' => 'Quote not found.'
                ]);
            }

            $deliveryMethod = (string) $this->getRequest()->getParam('delivery_method', 'standard');
            $normalizedMethod = $this->deliveryFeeManager->normalizeMethod($deliveryMethod);

            $quote->setTotalsCollectedFlag(false);
            $quote->collectTotals();
            $fee = $this->deliveryFeeManager->applyToQuote($quote, $normalizedMethod);

            $this->cartRepository->save($quote);

            return $result->setData([
                'success' => true,
                'delivery_method' => $normalizedMethod,
                'delivery_fee' => $fee,
                'grand_total' => (float) $quote->getGrandTotal()
            ]);
        } catch (\Throwable $throwable) {
            return $result->setData([
                'success' => false,
                'message' => 'Unable to save delivery method.'
            ]);
        }
    }
}
