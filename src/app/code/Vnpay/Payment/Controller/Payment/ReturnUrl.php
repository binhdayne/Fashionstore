<?php
declare(strict_types=1);

namespace Vnpay\Payment\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Controller\Result\Redirect as ResultRedirect;
use Psr\Log\LoggerInterface;
use Vnpay\Payment\Model\Config;
use Vnpay\Payment\Model\VnpaySignature;

class ReturnUrl extends Action
{
    private Config $config;
    private VnpaySignature $signature;
    private LoggerInterface $logger;
    private CheckoutSession $checkoutSession;

    public function __construct(
        Context $context,
        Config $config,
        VnpaySignature $signature,
        LoggerInterface $logger,
        CheckoutSession $checkoutSession
    ) {
        parent::__construct($context);
        $this->config = $config;
        $this->signature = $signature;
        $this->logger = $logger;
        $this->checkoutSession = $checkoutSession;
    }

    public function execute(): ResultRedirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            $params = $this->getRequest()->getParams();
            $receivedHash = (string) ($params['vnp_SecureHash'] ?? '');

            // Remove signature parameters before re-sign verification.
            unset($params['vnp_SecureHash'], $params['vnp_SecureHashType']);
            ksort($params);

            $secret = $this->config->getHashSecret();
            if ($receivedHash === '' || !$this->signature->isValid($params, $receivedHash, $secret)) {
                $this->messageManager->addErrorMessage(__('Chu ky VNPAY khong hop le.'));

                return $resultRedirect->setPath('checkout/cart');
            }

            $responseCode = (string) ($params['vnp_ResponseCode'] ?? '');

            // Only show customer result here; order state changes are handled by IPN endpoint.
            if ($responseCode === '00') {
                $this->checkoutSession->setData('fashionstore_vnpay_returned', true);
                $this->checkoutSession->unsetData('fashionstore_vnpay_success_redirected');
                $this->messageManager->addSuccessMessage(__('Thanh toan VNPAY thanh cong.'));

                return $resultRedirect->setPath('checkout/onepage/success');
            }

            $this->messageManager->addErrorMessage(__('Thanh toan VNPAY that bai. Ma loi: %1', $responseCode));
            $this->checkoutSession->unsetData('fashionstore_vnpay_success_redirected');

            return $resultRedirect->setPath('checkout/cart');
        } catch (\Throwable $e) {
            $this->logger->error('VNPAY return handler error', ['exception' => $e]);
            $this->messageManager->addErrorMessage(__('Khong the xu ly ket qua VNPAY.'));

            return $resultRedirect->setPath('checkout/cart');
        }
    }
}
