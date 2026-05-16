<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Model\Zalopay;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Config as OrderConfig;

class OrderService
{
    private const PAYMENT_METHOD_CODE = 'fashionstore_zalopay';
    private const INFO_APP_TRANS_ID = 'zalopay_app_trans_id';
    private const INFO_APP_TIME = 'zalopay_app_time';
    private const INFO_ORDER_URL = 'zalopay_order_url';
    private const INFO_ORDER_TOKEN = 'zalopay_order_token';
    private const INFO_QR_CODE = 'zalopay_qr_code';
    private const INFO_ZP_TRANS_TOKEN = 'zalopay_zp_trans_token';
    private const INFO_ZP_TRANS_ID = 'zalopay_zp_trans_id';
    private const INFO_STATUS = 'zalopay_status';
    private const INFO_RAW_CREATE_RESPONSE = 'zalopay_create_response';
    private const INFO_RAW_QUERY_RESPONSE = 'zalopay_query_response';
    private const STATUS_CREATED = 'created';
    private const STATUS_PENDING = 'pending';
    private const STATUS_PAID = 'paid';

    private Config $config;

    private ApiClient $apiClient;

    private Json $jsonSerializer;

    private OrderRepositoryInterface $orderRepository;

    private SearchCriteriaBuilder $searchCriteriaBuilder;

    private OrderConfig $orderConfig;

    public function __construct(
        Config $config,
        ApiClient $apiClient,
        Json $jsonSerializer,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        OrderConfig $orderConfig
    ) {
        $this->config = $config;
        $this->apiClient = $apiClient;
        $this->jsonSerializer = $jsonSerializer;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->orderConfig = $orderConfig;
    }

    public function createForOrder(OrderInterface $order, bool $force = false): array
    {
        if ($order->getPayment() === null || $order->getPayment()->getMethod() !== self::PAYMENT_METHOD_CODE) {
            throw new LocalizedException(__('This order is not using ZaloPay.'));
        }

        if (!$this->config->isConfigured((int) $order->getStoreId())) {
            throw new LocalizedException(__('ZaloPay is not configured yet.'));
        }

        $existingOrderUrl = (string) $order->getPayment()->getAdditionalInformation(self::INFO_ORDER_URL);
        $existingAppTransId = (string) $order->getPayment()->getAdditionalInformation(self::INFO_APP_TRANS_ID);
        if (!$force && $existingOrderUrl !== '' && $existingAppTransId !== '') {
            return $this->buildFrontendPayload($order, [
                'order_url' => $existingOrderUrl,
                'order_token' => (string) $order->getPayment()->getAdditionalInformation(self::INFO_ORDER_TOKEN),
                'qr_code' => (string) $order->getPayment()->getAdditionalInformation(self::INFO_QR_CODE),
                'zp_trans_token' => (string) $order->getPayment()->getAdditionalInformation(self::INFO_ZP_TRANS_TOKEN),
                'app_trans_id' => $existingAppTransId,
                'return_code' => 1,
                'sub_return_code' => 1,
            ]);
        }

        $appTime = $this->getCurrentMilliseconds();
        $appTransId = $this->generateAppTransId($order, $force);
        $itemPayload = $this->buildItemPayload($order);
        $embedData = $this->buildEmbedData($order);
        $amount = (int) round((float) $order->getGrandTotal());
        $payload = [
            'app_id' => $this->config->getAppId((int) $order->getStoreId()),
            'app_user' => $this->buildAppUser($order),
            'app_trans_id' => $appTransId,
            'app_time' => $appTime,
            'expire_duration_seconds' => $this->config->getExpireDurationSeconds((int) $order->getStoreId()),
            'amount' => $amount,
            'description' => sprintf('Thanh toan don hang #%s', (string) $order->getIncrementId()),
            'callback_url' => $this->config->getCallbackUrl((int) $order->getStoreId()),
            'item' => $itemPayload,
            'embed_data' => $embedData,
            'bank_code' => '',
        ];
        $payload['mac'] = $this->signCreatePayload($payload, (int) $order->getStoreId());

        $response = $this->apiClient->postJson($this->config->getCreateEndpoint((int) $order->getStoreId()), $payload);

        if ((int) ($response['return_code'] ?? 0) !== 1 || (int) ($response['sub_return_code'] ?? 0) !== 1) {
            throw new LocalizedException(__(
                'ZaloPay create order failed: %1',
                (string) ($response['sub_return_message'] ?? $response['return_message'] ?? 'Unknown error')
            ));
        }

        $payment = $order->getPayment();
        $payment->setAdditionalInformation(self::INFO_APP_TRANS_ID, $appTransId);
        $payment->setAdditionalInformation(self::INFO_APP_TIME, $appTime);
        $payment->setAdditionalInformation(self::INFO_ORDER_URL, (string) ($response['order_url'] ?? ''));
        $payment->setAdditionalInformation(self::INFO_ORDER_TOKEN, (string) ($response['order_token'] ?? ''));
        $payment->setAdditionalInformation(self::INFO_QR_CODE, (string) ($response['qr_code'] ?? ''));
        $payment->setAdditionalInformation(self::INFO_ZP_TRANS_TOKEN, (string) ($response['zp_trans_token'] ?? ''));
        $payment->setAdditionalInformation(self::INFO_STATUS, self::STATUS_CREATED);
        $payment->setAdditionalInformation(self::INFO_RAW_CREATE_RESPONSE, $this->jsonSerializer->serialize($response));

        $this->markAsPendingPayment($order, 'Da tao ma thanh toan ZaloPay.');

        return $this->buildFrontendPayload($order, $response + ['app_trans_id' => $appTransId]);
    }

    public function queryForOrder(OrderInterface $order): array
    {
        $payment = $order->getPayment();
        if ($payment === null) {
            throw new LocalizedException(__('This order does not contain payment information.'));
        }

        $appTransId = (string) $payment->getAdditionalInformation(self::INFO_APP_TRANS_ID);
        if ($appTransId === '') {
            throw new LocalizedException(__('ZaloPay transaction information is missing.'));
        }

        if ((string) $payment->getAdditionalInformation(self::INFO_STATUS) === self::STATUS_PAID) {
            return $this->buildStatusPayload($order, ['return_code' => 1, 'sub_return_code' => 1, 'zp_trans_id' => $payment->getAdditionalInformation(self::INFO_ZP_TRANS_ID)]);
        }

        $payload = [
            'app_id' => $this->config->getAppId((int) $order->getStoreId()),
            'app_trans_id' => $appTransId,
            'mac' => $this->signQueryPayload($appTransId, (int) $order->getStoreId()),
        ];
        $response = $this->apiClient->postJson($this->config->getQueryEndpoint((int) $order->getStoreId()), $payload);

        $payment->setAdditionalInformation(self::INFO_RAW_QUERY_RESPONSE, $this->jsonSerializer->serialize($response));
        if ($this->isPaidResponse($response)) {
            $this->markAsPaid($order, $response, true);
        }

        return $this->buildStatusPayload($order, $response);
    }

    public function handleCallback(array $callbackPayload): array
    {
        $data = (string) ($callbackPayload['data'] ?? '');
        $receivedMac = (string) ($callbackPayload['mac'] ?? '');

        if ($data === '' || $receivedMac === '') {
            return ['return_code' => 2, 'return_message' => 'invalid'];
        }

        $calculatedMac = hash_hmac('sha256', $data, $this->config->getKey2());
        if (!hash_equals($calculatedMac, $receivedMac)) {
            return ['return_code' => 2, 'return_message' => 'invalid'];
        }

        try {
            $decodedData = $this->jsonSerializer->unserialize($data);
        } catch (\InvalidArgumentException $exception) {
            return ['return_code' => 2, 'return_message' => 'invalid'];
        }

        if (!is_array($decodedData)) {
            return ['return_code' => 2, 'return_message' => 'invalid'];
        }

        $order = $this->getOrderByAppTransId((string) ($decodedData['app_trans_id'] ?? ''));
        if ($order === null) {
            return ['return_code' => 2, 'return_message' => 'invalid'];
        }

        $this->markAsPaid($order, $decodedData, false);

        return ['return_code' => 1, 'return_message' => 'success'];
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

    private function buildFrontendPayload(OrderInterface $order, array $response): array
    {
        return [
            'order_increment_id' => (string) $order->getIncrementId(),
            'app_trans_id' => (string) ($response['app_trans_id'] ?? ''),
            'order_url' => (string) ($response['order_url'] ?? ''),
            'order_token' => (string) ($response['order_token'] ?? ''),
            'zp_trans_token' => (string) ($response['zp_trans_token'] ?? ''),
            'qr_code' => (string) ($response['qr_code'] ?? ''),
            'redirect_url' => $this->config->getRedirectUrl((int) $order->getStoreId()),
            'expire_duration_seconds' => $this->config->getExpireDurationSeconds((int) $order->getStoreId()),
            'grand_total' => (float) $order->getGrandTotal(),
            'currency_code' => (string) $order->getOrderCurrencyCode(),
        ];
    }

    private function buildStatusPayload(OrderInterface $order, array $response): array
    {
        $payment = $order->getPayment();
        $status = (string) ($payment?->getAdditionalInformation(self::INFO_STATUS) ?? self::STATUS_PENDING);

        return [
            'paid' => $status === self::STATUS_PAID,
            'status' => $status,
            'is_processing' => (bool) ($response['is_processing'] ?? false),
            'return_code' => (int) ($response['return_code'] ?? 0),
            'sub_return_code' => (int) ($response['sub_return_code'] ?? 0),
            'return_message' => (string) ($response['return_message'] ?? ''),
            'sub_return_message' => (string) ($response['sub_return_message'] ?? ''),
            'zp_trans_id' => (string) ($response['zp_trans_id'] ?? $payment?->getAdditionalInformation(self::INFO_ZP_TRANS_ID) ?? ''),
            'redirect_url' => $this->config->getRedirectUrl((int) $order->getStoreId()),
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
        $order->addCommentToStatusHistory($comment);
        $this->orderRepository->save($order);
    }

    private function markAsPaid(OrderInterface $order, array $response, bool $queried): void
    {
        $payment = $order->getPayment();
        if ($payment === null) {
            return;
        }

        $payment->setAdditionalInformation(self::INFO_STATUS, self::STATUS_PAID);
        $payment->setAdditionalInformation(self::INFO_ZP_TRANS_ID, (string) ($response['zp_trans_id'] ?? ''));

        if ($order instanceof Order && $order->getState() !== Order::STATE_PROCESSING) {
            $order->setState(Order::STATE_PROCESSING);
            $order->setStatus($this->getDefaultStatus(Order::STATE_PROCESSING));
            $order->addCommentToStatusHistory(
                $queried
                    ? 'Da xac nhan thanh toan ZaloPay qua truy van trang thai.'
                    : 'Da nhan callback thanh cong tu ZaloPay.'
            );
        }

        $this->orderRepository->save($order);
    }

    private function isPaidResponse(array $response): bool
    {
        return (int) ($response['return_code'] ?? 0) === 1
            && (int) ($response['sub_return_code'] ?? 0) === 1
            && !empty($response['zp_trans_id']);
    }

    private function generateAppTransId(OrderInterface $order, bool $force = false): string
    {
        $date = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Ho_Chi_Minh'));

        $baseId = $date->format('ymd') . '_' . (string) $order->getIncrementId();

        if (!$force) {
            return $baseId;
        }

        return sprintf('%s_%s', $baseId, substr((string) $this->getCurrentMilliseconds(), -6));
    }

    private function buildAppUser(OrderInterface $order): string
    {
        $customerEmail = trim((string) $order->getCustomerEmail());

        return $customerEmail !== '' ? $customerEmail : 'fashionstore';
    }

    private function buildItemPayload(OrderInterface $order): string
    {
        $items = [];

        foreach ($order->getAllVisibleItems() as $item) {
            $items[] = [
                'itemid' => (string) $item->getSku(),
                'itemname' => (string) $item->getName(),
                'itemprice' => (int) round((float) $item->getPriceInclTax()),
                'itemquantity' => (int) $item->getQtyOrdered(),
            ];
        }

        return $this->jsonSerializer->serialize($items);
    }

    private function buildEmbedData(OrderInterface $order): string
    {
        $storeId = (int) $order->getStoreId();
        $preferredPaymentMethod = $this->decodePreferredPaymentMethod($storeId);

        $payload = [
            'redirecturl' => $this->config->getRedirectUrl($storeId),
            'merchantinfo' => (string) $order->getIncrementId(),
        ];

        if ($preferredPaymentMethod !== []) {
            $payload['preferred_payment_method'] = $preferredPaymentMethod;
        }

        return $this->jsonSerializer->serialize($payload);
    }

    private function decodePreferredPaymentMethod(?int $storeId = null): array
    {
        if ($this->config->isSampleSandboxConfig($storeId)) {
            return [];
        }

        $preferredPaymentMethod = $this->config->getPreferredPaymentMethod($storeId);

        if ($preferredPaymentMethod === '') {
            return [];
        }

        try {
            $decodedValue = $this->jsonSerializer->unserialize($preferredPaymentMethod);
        } catch (\InvalidArgumentException $exception) {
            return [];
        }

        if (!is_array($decodedValue)) {
            return [];
        }

        return array_values(array_filter($decodedValue, static function ($value): bool {
            return trim((string) $value) !== '';
        }));
    }

    private function signCreatePayload(array $payload, int $storeId): string
    {
        $signature = implode('|', [
            (string) $payload['app_id'],
            (string) $payload['app_trans_id'],
            (string) $payload['app_user'],
            (string) $payload['amount'],
            (string) $payload['app_time'],
            (string) $payload['embed_data'],
            (string) $payload['item'],
        ]);

        return hash_hmac('sha256', $signature, $this->config->getKey1($storeId));
    }

    private function signQueryPayload(string $appTransId, int $storeId): string
    {
        $key1 = $this->config->getKey1($storeId);
        $signature = implode('|', [
            (string) $this->config->getAppId($storeId),
            $appTransId,
            $key1,
        ]);

        return hash_hmac('sha256', $signature, $key1);
    }

    private function getCurrentMilliseconds(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    private function getOrderByAppTransId(string $appTransId): ?OrderInterface
    {
        $incrementId = $this->extractIncrementIdFromAppTransId($appTransId);

        return $incrementId !== '' ? $this->getOrderByIncrementId($incrementId) : null;
    }

    private function extractIncrementIdFromAppTransId(string $appTransId): string
    {
        $parts = explode('_', $appTransId, 2);

        return $parts[1] ?? '';
    }

    private function getDefaultStatus(string $state): string
    {
        return (string) ($this->orderConfig->getStateDefaultStatus($state) ?: Order::STATE_PENDING_PAYMENT);
    }
}