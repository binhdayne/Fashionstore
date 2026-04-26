<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Plugin\Checkout;

use Magento\Checkout\Controller\Onepage\Success;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Sales\Model\Order;

class RedirectGatewayPaymentOnSuccessPlugin
{
    private const SESSION_KEY_REDIRECTED = 'fashionstore_vnpay_success_redirected';
    private const SESSION_KEY_RETURNED = 'fashionstore_vnpay_returned';

    private const REDIRECT_METHODS = [
        'vnpay',
        'fashionstore_vnpay',
        'fashionstore_momo',
    ];

    private CheckoutSession $checkoutSession;

    private RedirectFactory $resultRedirectFactory;

    public function __construct(
        CheckoutSession $checkoutSession,
        RedirectFactory $resultRedirectFactory
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->resultRedirectFactory = $resultRedirectFactory;
    }

    public function aroundExecute(Success $subject, callable $proceed): ResultInterface
    {
        // Allow customer to land on success page after returning from VNPAY.
        if ((bool) $this->checkoutSession->getData(self::SESSION_KEY_RETURNED)) {
            $this->checkoutSession->unsetData(self::SESSION_KEY_RETURNED);
            $this->checkoutSession->unsetData(self::SESSION_KEY_REDIRECTED);

            return $proceed();
        }

        /** @var Order $order */
        $order = $this->checkoutSession->getLastRealOrder();
        if (!$order || !$order->getEntityId()) {
            return $proceed();
        }

        $payment = $order->getPayment();
        $methodCode = $payment ? (string) $payment->getMethod() : '';
        if (!in_array($methodCode, self::REDIRECT_METHODS, true)) {
            return $proceed();
        }

        // Prevent loops if customer refreshes success before VNPAY return callback.
        if ((bool) $this->checkoutSession->getData(self::SESSION_KEY_REDIRECTED)) {
            return $proceed();
        }

        $this->checkoutSession->setData(self::SESSION_KEY_REDIRECTED, true);

        $resultRedirect = $this->resultRedirectFactory->create();

        return $resultRedirect->setPath('vnpay/payment/redirect');
    }
}
