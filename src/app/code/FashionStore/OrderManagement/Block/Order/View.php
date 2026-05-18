<?php
declare(strict_types=1);

namespace FashionStore\OrderManagement\Block\Order;

use FashionStore\OrderManagement\Model\ReviewEligibility;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Sales\Model\Order;

class View extends Template
{
    private ProductRepositoryInterface $productRepository;
    private ImageHelper $imageHelper;
    private Registry $registry;
    private PriceHelper $priceHelper;
    private FormKey $formKey;
    private CustomerSession $customerSession;
    private ReviewEligibility $reviewEligibility;

    public function __construct(
        Template\Context $context,
        ProductRepositoryInterface $productRepository,
        ImageHelper $imageHelper,
        Registry $registry,
        PriceHelper $priceHelper,
        FormKey $formKey,
        CustomerSession $customerSession,
        ReviewEligibility $reviewEligibility,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->productRepository = $productRepository;
        $this->imageHelper = $imageHelper;
        $this->registry = $registry;
        $this->priceHelper = $priceHelper;
        $this->formKey = $formKey;
        $this->customerSession = $customerSession;
        $this->reviewEligibility = $reviewEligibility;
    }

    public function getOrder(): ?Order
    {
        return $this->registry->registry('current_order');
    }

    public function getOrderItems(): array
    {
        $order = $this->getOrder();
        if (!$order) {
            return [];
        }

        $items = [];
        $customerId = (int) ($order->getCustomerId() ?: $this->customerSession->getCustomerId());
        $isComplete = $order->getState() === Order::STATE_COMPLETE;

        foreach ($order->getAllVisibleItems() as $item) {
            $productId = (int) $item->getProductId();
            $orderItemId = (int) $item->getItemId();
            $imageUrl = $this->getProductImageUrl(
                $productId,
                (string) $item->getSku()
            );
            $hasReviewed = $customerId > 0
                && $orderItemId > 0
                && $this->reviewEligibility->hasCustomerReviewedOrderItem($customerId, $orderItemId);
            $canReview = $isComplete
                && $customerId > 0
                && $productId > 0
                && $orderItemId > 0
                && !$hasReviewed;
            $options = [];
            if ($item->getProductOptions()) {
                $opts = $item->getProductOptions();
                if (!empty($opts['attributes_info'])) {
                    foreach ($opts['attributes_info'] as $opt) {
                        $options[] = $opt['label'] . ': ' . $opt['value'];
                    }
                }
            }
            $items[] = [
                'product_id' => $productId,
                'order_item_id' => $orderItemId,
                'name' => $item->getName(),
                'sku' => $item->getSku(),
                'image_url' => $imageUrl,
                'qty' => (int) $item->getQtyOrdered(),
                'price' => $this->priceHelper->currency((float) $item->getPrice(), true, false),
                'subtotal' => $this->priceHelper->currency((float) $item->getRowTotal(), true, false),
                'options' => $options,
                'can_review' => $canReview,
                'has_reviewed' => $hasReviewed,
                'review_url' => $this->getProductReviewUrl($productId, $orderItemId),
            ];
        }

        return $items;
    }

    public function getOrderTotals(): array
    {
        $order = $this->getOrder();
        if (!$order) {
            return [];
        }

        $totals = [
            [
                'label' => 'Tạm tính',
                'value' => $this->priceHelper->currency((float) $order->getSubtotal(), true, false),
            ],
        ];

        if ((float) $order->getShippingAmount() > 0) {
            $shippingLabel = 'Phí vận chuyển';
            if ($order->getShippingDescription()) {
                $shippingLabel .= ' (' . $order->getShippingDescription() . ')';
            }
            $totals[] = [
                'label' => $shippingLabel,
                'value' => $this->priceHelper->currency((float) $order->getShippingAmount(), true, false),
            ];
        }

        if ((float) $order->getDiscountAmount() != 0.0) {
            $totals[] = [
                'label' => 'Giảm giá',
                'value' => $this->priceHelper->currency((float) $order->getDiscountAmount(), true, false),
            ];
        }

        $totals[] = [
            'label' => 'Tổng thanh toán',
            'value' => $this->priceHelper->currency((float) $order->getGrandTotal(), true, false),
            'is_grand' => true,
        ];

        return $totals;
    }

    public function getPaymentMethodTitle(): string
    {
        $order = $this->getOrder();
        if (!$order) {
            return '';
        }

        try {
            return (string) $order->getPayment()->getMethodInstance()->getTitle();
        } catch (\Throwable $e) {
            return (string) $order->getPayment()->getMethod();
        }
    }

    public function getStatusLabel(): string
    {
        $order = $this->getOrder();
        return $order ? (string) $order->getStatusLabel() : '';
    }

    public function getStatusClass(): string
    {
        $order = $this->getOrder();
        if (!$order) {
            return '';
        }

        $map = [
            'pending' => 'pending',
            'new' => 'pending',
            'processing' => 'processing',
            'complete' => 'complete',
            'canceled' => 'canceled',
        ];

        return $map[(string) $order->getState()] ?? 'default';
    }

    public function canCancel(): bool
    {
        $order = $this->getOrder();
        return (bool) ($order && $order->canCancel() && $order->getStatus() === 'pending');
    }

    public function getCancelUrl(): string
    {
        return $this->getUrl('fashionstore_order/order/cancel');
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('sales/order/history');
    }

    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }

    private function getProductReviewUrl(int $productId, int $orderItemId): string
    {
        if ($productId <= 0) {
            return '#';
        }

        return $this->getUrl(
            'review/product/list',
            [
                'id' => $productId,
                'order_item_id' => $orderItemId,
                '_fragment' => 'review-form',
            ]
        );
    }

    private function getProductImageUrl(int $productId, string $sku): string
    {
        try {
            if ($productId > 0) {
                $product = $this->productRepository->getById($productId, false, null, true);
            } else {
                $product = $this->productRepository->get($sku);
            }

            $imageFile = (string) $product->getThumbnail();
            if ($imageFile === '' || $imageFile === 'no_selection') {
                $imageFile = (string) $product->getSmallImage();
            }
            if ($imageFile === '' || $imageFile === 'no_selection') {
                $imageFile = (string) $product->getImage();
            }

            return $this->imageHelper
                ->init($product, 'product_thumbnail_image')
                ->setImageFile($imageFile)
                ->resize(100, 100)
                ->getUrl();
        } catch (\Throwable $e) {
            return $this->getViewFileUrl('Magento_Catalog::images/product/placeholder/thumbnail.jpg');
        }
    }
}
