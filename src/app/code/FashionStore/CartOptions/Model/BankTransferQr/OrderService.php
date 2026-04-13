<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Model\BankTransferQr;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Config as OrderConfig;

class OrderService
{
    private const PAYMENT_METHOD_CODE = 'fashionstore_banktransfer_qr';
    private const INFO_STATUS = 'banktransfer_qr_status';
    private const INFO_PROOF_IMAGE = 'banktransfer_qr_proof_image';
    private const INFO_PROOF_NOTE = 'banktransfer_qr_proof_note';
    private const INFO_PROOF_UPLOADED_AT = 'banktransfer_qr_proof_uploaded_at';
    private const STATUS_AWAITING_PROOF = 'awaiting_proof';
    private const STATUS_PROOF_UPLOADED = 'proof_uploaded';

    private OrderRepositoryInterface $orderRepository;

    private SearchCriteriaBuilder $searchCriteriaBuilder;

    private OrderConfig $orderConfig;

    private UrlInterface $urlBuilder;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        OrderConfig $orderConfig,
        UrlInterface $urlBuilder
    ) {
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->orderConfig = $orderConfig;
        $this->urlBuilder = $urlBuilder;
    }

    public function createForOrder(OrderInterface $order): array
    {
        $payment = $order->getPayment();
        if ($payment === null || $payment->getMethod() !== self::PAYMENT_METHOD_CODE) {
            throw new LocalizedException(__('This order is not using bank transfer QR payment.'));
        }

        if ((string) $payment->getAdditionalInformation(self::INFO_STATUS) === '') {
            $payment->setAdditionalInformation(self::INFO_STATUS, self::STATUS_AWAITING_PROOF);
            $this->markAsPendingPayment($order, 'Cho khach hang quet QR chuyen khoan va tai len minh chung thanh toan.');
        }

        return $this->buildFrontendPayload($order);
    }

    public function saveProofForOrder(OrderInterface $order, string $proofImage, string $proofNote = ''): array
    {
        $payment = $order->getPayment();
        if ($payment === null || $payment->getMethod() !== self::PAYMENT_METHOD_CODE) {
            throw new LocalizedException(__('This order is not using bank transfer QR payment.'));
        }

        $payment->setAdditionalInformation(self::INFO_STATUS, self::STATUS_PROOF_UPLOADED);
        $payment->setAdditionalInformation(self::INFO_PROOF_IMAGE, $proofImage);
        $payment->setAdditionalInformation(self::INFO_PROOF_NOTE, $proofNote);
        $payment->setAdditionalInformation(self::INFO_PROOF_UPLOADED_AT, gmdate('Y-m-d H:i:s'));

        if ($order instanceof Order) {
            $order->setState(Order::STATE_PENDING_PAYMENT);
            $order->setStatus($this->getDefaultStatus(Order::STATE_PENDING_PAYMENT));
            $order->addCommentToStatusHistory(__('Khach hang da tai len minh chung chuyen khoan QR.'));
        }

        $this->orderRepository->save($order);

        return $this->buildFrontendPayload($order);
    }

    public function getOrderByIncrementId(string $incrementId): ?OrderInterface
    {
        if ($incrementId === '') {
            return null;
        }

        $criteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', $incrementId)
            ->setPageSize(1)
            ->create();
        $items = $this->orderRepository->getList($criteria)->getItems();

        return $items !== [] ? reset($items) : null;
    }

    private function buildFrontendPayload(OrderInterface $order): array
    {
        $payment = $order->getPayment();

        return [
            'order_increment_id' => (string) $order->getIncrementId(),
            'grand_total' => (float) $order->getGrandTotal(),
            'currency_code' => (string) $order->getOrderCurrencyCode(),
            'redirect_url' => $this->urlBuilder->getUrl('checkout/onepage/success'),
            'status' => (string) ($payment?->getAdditionalInformation(self::INFO_STATUS) ?? self::STATUS_AWAITING_PROOF),
            'proof_image' => (string) ($payment?->getAdditionalInformation(self::INFO_PROOF_IMAGE) ?? ''),
            'proof_note' => (string) ($payment?->getAdditionalInformation(self::INFO_PROOF_NOTE) ?? ''),
        ];
    }

    private function markAsPendingPayment(OrderInterface $order, string $comment): void
    {
        if (!$order instanceof Order) {
            $this->orderRepository->save($order);
            return;
        }

        $order->setState(Order::STATE_PENDING_PAYMENT);
        $order->setStatus($this->getDefaultStatus(Order::STATE_PENDING_PAYMENT));
        $order->addCommentToStatusHistory(__($comment));
        $this->orderRepository->save($order);
    }

    private function getDefaultStatus(string $state): string
    {
        $statuses = $this->orderConfig->getStateStatuses($state, false);

        return $statuses !== [] ? (string) array_key_first($statuses) : Order::STATUS_PENDING_PAYMENT;
    }
}