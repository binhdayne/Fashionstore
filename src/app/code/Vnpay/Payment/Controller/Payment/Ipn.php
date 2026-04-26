<?php
declare(strict_types=1);

namespace Vnpay\Payment\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Psr\Log\LoggerInterface;
use Vnpay\Payment\Model\Config;
use Vnpay\Payment\Model\VnpaySignature;

class Ipn extends Action
{
    private JsonFactory $jsonFactory;
    private Config $config;
    private VnpaySignature $signature;
    private OrderFactory $orderFactory;
    private OrderRepositoryInterface $orderRepository;
    private LoggerInterface $logger;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        Config $config,
        VnpaySignature $signature,
        OrderFactory $orderFactory,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->config = $config;
        $this->signature = $signature;
        $this->orderFactory = $orderFactory;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();

        try {
            $params = $this->getRequest()->getParams();
            $receivedHash = (string) ($params['vnp_SecureHash'] ?? '');

            // Validate request signature before any business logic.
            unset($params['vnp_SecureHash'], $params['vnp_SecureHashType']);
            ksort($params);

            $secret = $this->config->getHashSecret();
            if ($receivedHash === '' || !$this->signature->isValid($params, $receivedHash, $secret)) {
                return $result->setData([
                    'RspCode' => '97',
                    'Message' => 'Invalid signature',
                ]);
            }

            $txnRef = (string) ($params['vnp_TxnRef'] ?? '');
            $responseCode = (string) ($params['vnp_ResponseCode'] ?? '');
            $vnpAmount = (int) ($params['vnp_Amount'] ?? 0);

            if ($txnRef === '') {
                return $result->setData([
                    'RspCode' => '01',
                    'Message' => 'Missing order reference',
                ]);
            }

            // Use increment ID sent by VNPAY to locate Magento order.
            $order = $this->orderFactory->create()->loadByIncrementId($txnRef);
            if (!$order->getEntityId()) {
                return $result->setData([
                    'RspCode' => '01',
                    'Message' => 'Order not found',
                ]);
            }

            $expectedAmount = (int) round((float) $order->getGrandTotal() * 100);
            if ($vnpAmount !== $expectedAmount) {
                return $result->setData([
                    'RspCode' => '04',
                    'Message' => 'Invalid amount',
                ]);
            }

            // Success code: mark order as processing (do not process twice).
            if ($responseCode === '00') {
                if (!$this->isOrderInSuccessfulState($order)) {
                    $order->setState(Order::STATE_PROCESSING);
                    $order->setStatus(Order::STATE_PROCESSING);
                    $order->addCommentToStatusHistory('VNPAY IPN confirmed successful payment.');
                    $this->orderRepository->save($order);
                }

                return $result->setData([
                    'RspCode' => '00',
                    'Message' => 'Confirm Success',
                ]);
            }

            // Non-success code: cancel order if possible.
            if ($order->canCancel()) {
                $order->cancel();
            } else {
                $order->setState(Order::STATE_CANCELED);
                $order->setStatus(Order::STATE_CANCELED);
            }

            $order->addCommentToStatusHistory(sprintf('VNPAY IPN failed. Response code: %s', $responseCode));
            $this->orderRepository->save($order);

            return $result->setData([
                'RspCode' => '00',
                'Message' => 'Confirm Failure',
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('VNPAY IPN handler error', ['exception' => $e]);

            return $result->setData([
                'RspCode' => '99',
                'Message' => 'Unknown error',
            ]);
        }
    }

    private function isOrderInSuccessfulState(Order $order): bool
    {
        return in_array((string) $order->getState(), [Order::STATE_PROCESSING, Order::STATE_COMPLETE], true);
    }
}
