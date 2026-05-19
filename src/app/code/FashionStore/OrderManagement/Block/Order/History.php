<?php
declare(strict_types=1);

namespace FashionStore\OrderManagement\Block\Order;

use FashionStore\OrderManagement\Model\ShippingTimeline;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\View\Element\Template;
use Magento\Sales\Block\Order\History as CoreHistory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;

class History extends CoreHistory
{
    private ShippingTimeline $shippingTimeline;

    public function __construct(
        Template\Context $context,
        CollectionFactory $orderCollectionFactory,
        CustomerSession $customerSession,
        \Magento\Sales\Model\Order\Config $orderConfig,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ImageHelper $imageHelper,
        private readonly PriceHelper $priceHelper,
        private readonly FormKey $formKey,
        array $data = [],
        ?ShippingTimeline $shippingTimeline = null
    ) {
        parent::__construct($context, $orderCollectionFactory, $customerSession, $orderConfig, $data);
        $this->shippingTimeline = $shippingTimeline
            ?? ObjectManager::getInstance()->get(ShippingTimeline::class);
        $this->setTemplate('FashionStore_OrderManagement::order/history.phtml');
    }

    /**
     * Skip the core pager setup because this block renders a normalized array,
     * not the original Magento order collection.
     */
    protected function _prepareLayout()
    {
        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOrders(): array
    {
        $orders = parent::getOrders();
        if (!$orders) {
            return [];
        }

        $result = [];
        foreach ($orders as $order) {
            if (!$order instanceof Order) {
                continue;
            }

            $items = [];
            foreach ($order->getAllVisibleItems() as $item) {
                $items[] = [
                    'name' => (string) $item->getName(),
                    'sku' => (string) $item->getSku(),
                    'qty' => (int) $item->getQtyOrdered(),
                    'price' => $this->priceHelper->currency((float) $item->getPrice(), true, false),
                    'image_url' => $this->getProductImageUrl((int) $item->getProductId()),
                ];
            }

            $result[] = [
                'id' => (int) $order->getId(),
                'increment_id' => (string) $order->getIncrementId(),
                'created_at' => (string) $order->getCreatedAt(),
                'state' => (string) $order->getState(),
                'status_label' => (string) $order->getStatusLabel(),
                'status' => (string) $order->getStatus(),
                'grand_total' => $this->priceHelper->currency((float) $order->getGrandTotal(), true, false),
                'can_cancel' => $order->canCancel() && $order->getStatus() === 'pending',
                'view_url' => $this->getViewUrl($order),
                'tracking' => $this->shippingTimeline->getTrackingData($order),
                'items' => $items,
            ];
        }

        return $result;
    }

    public function getOrdersByStatus(): array
    {
        return $this->getOrders();
    }

    /**
     * @return array<string, int>
     */
    public function getTabCounts(): array
    {
        $orders = $this->getOrders();
        $counts = [
            'all' => count($orders),
            'pending' => 0,
            'processing' => 0,
            'complete' => 0,
            'canceled' => 0,
        ];

        foreach ($orders as $order) {
            $status = (string) ($order['status'] ?? '');
            $state = (string) ($order['state'] ?? '');

            if ($status === 'pending' || $state === 'new') {
                $counts['pending']++;
            } elseif ($state === 'processing') {
                $counts['processing']++;
            } elseif ($state === 'complete') {
                $counts['complete']++;
            } elseif ($state === 'canceled') {
                $counts['canceled']++;
            }
        }

        return $counts;
    }

    public function getCancelUrl(): string
    {
        return $this->getUrl('fashionstore_order/order/cancel');
    }

    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }

    private function getProductImageUrl(int $productId): string
    {
        try {
            $product = $this->productRepository->getById($productId, false, null, true);
            return $this->imageHelper
                ->init($product, 'product_thumbnail_image')
                ->setImageFile($product->getThumbnail())
                ->resize(80, 80)
                ->getUrl();
        } catch (\Throwable) {
            return $this->getViewFileUrl('Magento_Catalog::images/product/placeholder/thumbnail.jpg');
        }
    }
}
