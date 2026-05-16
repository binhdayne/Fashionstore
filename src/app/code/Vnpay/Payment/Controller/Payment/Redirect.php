<?php
declare(strict_types=1);

namespace Vnpay\Payment\Controller\Payment;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect as ResultRedirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;
use Vnpay\Payment\Model\Config;
use Vnpay\Payment\Model\VnpaySignature;

class Redirect extends Action
{
    private CheckoutSession $checkoutSession;
    private UrlInterface $urlBuilder;
    private RemoteAddress $remoteAddress;
    private Config $config;
    private VnpaySignature $signature;
    private LoggerInterface $logger;

    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        UrlInterface $urlBuilder,
        RemoteAddress $remoteAddress,
        Config $config,
        VnpaySignature $signature,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->urlBuilder = $urlBuilder;
        $this->remoteAddress = $remoteAddress;
        $this->config = $config;
        $this->signature = $signature;
        $this->logger = $logger;
    }

    public function execute(): ResultRedirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            // Load the latest real order created from checkout.
            $order = $this->checkoutSession->getLastRealOrder();
            if (!$order || !$order->getEntityId()) {
                throw new LocalizedException(__('Cannot find order for VNPAY redirect.'));
            }

            $storeId = (int) $order->getStoreId();
            $tmnCode = $this->config->getTmnCode($storeId);
            $hashSecret = $this->config->getHashSecret($storeId);
            $paymentUrl = $this->config->getPaymentUrl($storeId);

            if ($tmnCode === '' || $hashSecret === '' || $paymentUrl === '') {
                $this->messageManager->addErrorMessage(__('VNPAY chua duoc cau hinh day du. Vui long nhap lai TMN Code va Hash Secret.'));

                return $resultRedirect->setPath('checkout/cart');
            }

            if (!$this->config->isConfigured($storeId)) {
                throw new LocalizedException(__('VNPAY configuration is incomplete.'));
            }

            // Build return URL and amount according to VNPAY protocol.
            $returnUrl = $this->urlBuilder->getUrl('vnpay/payment/returnUrl', ['_secure' => true]);
            $amount = (int) round((float) $order->getGrandTotal() * 100);

            $params = [
                'vnp_Version' => '2.1.0',
                'vnp_Command' => 'pay',
                'vnp_TmnCode' => $tmnCode,
                'vnp_Amount' => $amount,
                'vnp_CreateDate' => date('YmdHis'),
                'vnp_CurrCode' => 'VND',
                'vnp_IpAddr' => (string) ($this->remoteAddress->getRemoteAddress() ?: '127.0.0.1'),
                'vnp_Locale' => 'vn',
                'vnp_OrderInfo' => sprintf('Thanh toan don hang %s', $order->getIncrementId()),
                'vnp_OrderType' => 'other',
                'vnp_ReturnUrl' => $returnUrl,
                'vnp_TxnRef' => (string) $order->getIncrementId(),
            ];

            // Do not force vnp_BankCode in sandbox because some merchant test profiles
            // return code=76 (unsupported bank) when a fixed bank code is provided.

            ksort($params);
            $secureHash = $this->signature->makeSignature($params, $hashSecret);
            $params['vnp_SecureHash'] = $secureHash;

            $queryString = http_build_query($params);
            $redirectUrl = rtrim($paymentUrl, '?') . '?' . $queryString;

            return $resultRedirect->setUrl($redirectUrl);
        } catch (\Throwable $e) {
            $this->logger->error('VNPAY redirect error', ['exception' => $e]);
            $this->messageManager->addErrorMessage(__('Cannot initialize VNPAY payment.'));

            return $resultRedirect->setPath('checkout/cart');
        }
    }
}
