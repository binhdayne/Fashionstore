<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Cron;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class CancelStalePendingPayments
{
    private const ZALOPAY_METHOD = 'fashionstore_zalopay';
    private const BANKTRANSFER_METHOD = 'fashionstore_banktransfer_qr';
    private const ZALOPAY_STATUS = 'zalopay_status';
    private const ZALOPAY_STATUS_PAID = 'paid';
    private const BANKTRANSFER_STATUS = 'banktransfer_qr_status';
    private const BANKTRANSFER_PROOF_IMAGE = 'banktransfer_qr_proof_image';
    private const BANKTRANSFER_STATUS_AWAITING_PROOF = 'awaiting_proof';
    private const XML_PATH_ZALOPAY_CANCEL_MINUTES = 'payment/fashionstore_zalopay/cancel_pending_after_minutes';
    private const XML_PATH_BANKTRANSFER_CANCEL_MINUTES = 'payment/fashionstore_banktransfer_qr/cancel_pending_after_minutes';
    private const DEFAULT_ZALOPAY_CANCEL_MINUTES = 60;
    private const DEFAULT_BANKTRANSFER_CANCEL_MINUTES = 1440;

    private OrderRepositoryInterface $orderRepository;

    private SearchCriteriaBuilder $searchCriteriaBuilder;

    private ScopeConfigInterface $scopeConfig;

    private LoggerInterface $logger;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        $this->cancelStaleOrders(
            self::ZALOPAY_METHOD,
            $this->getTimeoutMinutes(self::XML_PATH_ZALOPAY_CANCEL_MINUTES, self::DEFAULT_ZALOPAY_CANCEL_MINUTES)
        );
        $this->cancelStaleOrders(
            self::BANKTRANSFER_METHOD,
            $this->getTimeoutMinutes(
                self::XML_PATH_BANKTRANSFER_CANCEL_MINUTES,
                self::DEFAULT_BANKTRANSFER_CANCEL_MINUTES
            )
        );
    }

    private function cancelStaleOrders(string $paymentMethod, int $timeoutMinutes): void
    {
        if ($timeoutMinutes <= 0) {
            return;
        }

        $cutoff = gmdate('Y-m-d H:i:s', time() - ($timeoutMinutes * 60));
        $criteria = $this->searchCriteriaBuilder
            ->addFilter(OrderInterface::STATE, Order::STATE_PENDING_PAYMENT)
            ->addFilter('created_at', $cutoff, 'lteq')
            ->setPageSize(100)
            ->create();

        foreach ($this->orderRepository->getList($criteria)->getItems() as $order) {
            if (!$order instanceof Order || !$this->shouldCancel($order, $paymentMethod)) {
                continue;
            }

            try {
                $this->cancelOrder($order, $paymentMethod, $timeoutMinutes);
            } catch (\Throwable $exception) {
                $this->logger->error('Unable to auto-cancel stale pending payment order', [
                    'order_increment_id' => $order->getIncrementId(),
                    'payment_method' => $paymentMethod,
                    'exception' => $exception,
                ]);
            }
        }
    }

    private function shouldCancel(Order $order, string $paymentMethod): bool
    {
        $payment = $order->getPayment();
        if ($payment === null || $payment->getMethod() !== $paymentMethod || !$order->canCancel()) {
            return false;
        }

        if ($paymentMethod === self::ZALOPAY_METHOD) {
            return (string) $payment->getAdditionalInformation(self::ZALOPAY_STATUS) !== self::ZALOPAY_STATUS_PAID;
        }

        if ($paymentMethod === self::BANKTRANSFER_METHOD) {
            $status = (string) $payment->getAdditionalInformation(self::BANKTRANSFER_STATUS);
            $proofImage = trim((string) $payment->getAdditionalInformation(self::BANKTRANSFER_PROOF_IMAGE));

            return $proofImage === ''
                && ($status === '' || $status === self::BANKTRANSFER_STATUS_AWAITING_PROOF);
        }

        return false;
    }

    private function cancelOrder(Order $order, string $paymentMethod, int $timeoutMinutes): void
    {
        $order->cancel();
        $order->addCommentToStatusHistory(
            sprintf(
                'Auto-canceled stale %s order after %d minutes without payment confirmation.',
                $paymentMethod,
                $timeoutMinutes
            )
        );
        $this->orderRepository->save($order);
    }

    private function getTimeoutMinutes(string $path, int $defaultMinutes): int
    {
        $value = (int) $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE);

        return $value > 0 ? $value : $defaultMinutes;
    }
}
