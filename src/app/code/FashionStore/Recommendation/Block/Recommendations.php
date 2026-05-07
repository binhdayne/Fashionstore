<?php

namespace FashionStore\Recommendation\Block;

use FashionStore\Recommendation\Model\Config;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Model\Visitor;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Registry;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Template;

class Recommendations extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly CustomerSession $customerSession,
        private readonly Visitor $visitor,
        private readonly Registry $registry,
        private readonly Config $config,
        private readonly CollectionFactory $productCollectionFactory,
        private readonly Visibility $productVisibility,
        private readonly ImageHelper $imageHelper,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly ResourceConnection $resourceConnection,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isEnabled(): bool
    {
        return $this->config->isEnabled();
    }

    public function getAjaxUrl(): string
    {
        return $this->getUrl('fashionstore_recommendation/ajax/index');
    }

    public function getCurrentUserIdentifier(): string
    {
        if ($this->customerSession->isLoggedIn()) {
            return 'customer:' . (int) $this->customerSession->getCustomerId();
        }

        $visitorId = (int) $this->visitor->getId();
        if ($visitorId > 0) {
            return 'visitor:' . $visitorId;
        }

        return 'guest';
    }

    public function getCurrentProductId(): ?int
    {
        $product = $this->registry->registry('current_product');

        return $product ? (int) $product->getId() : null;
    }

    public function getCurrentCategoryId(): ?int
    {
        $category = $this->registry->registry('current_category');

        return $category ? (int) $category->getId() : null;
    }

    public function getDefaultTopK(): int
    {
        return $this->config->getTopK();
    }

    public function getFallbackItemsJson(int $limit = 4): string
    {
        return (string) json_encode(
            $this->getFallbackItems($limit),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    private function getFallbackItems(int $limit): array
    {
        $limit = max(1, $limit);
        $fallbackIds = $this->getBestSellerProductIdsByCategory($this->getCurrentCategoryId(), $limit);

        if (count($fallbackIds) < $limit) {
            $newestIds = $this->getNewestProductIdsByCategory($this->getCurrentCategoryId(), $limit * 2);
            foreach ($newestIds as $newestId) {
                if (!in_array($newestId, $fallbackIds, true)) {
                    $fallbackIds[] = $newestId;
                }
                if (count($fallbackIds) >= $limit) {
                    break;
                }
            }
        }

        if ($fallbackIds === []) {
            return [];
        }

        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect(['name', 'price', 'image', 'small_image', 'thumbnail', 'url_key']);
        $collection->addIdFilter($fallbackIds);
        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        $collection->setVisibility($this->productVisibility->getVisibleInSiteIds());
        $collection->addUrlRewrite();

        $productMap = [];
        foreach ($collection as $product) {
            $productMap[(int) $product->getId()] = [
                'product_id' => (int) $product->getId(),
                'name' => (string) $product->getName(),
                'url' => (string) $product->getProductUrl(),
                'image' => $this->getProductImageUrl($product),
                'price_html' => $this->priceCurrency->convertAndFormat((float) $product->getFinalPrice()),
                'reason' => 'Gợi ý phổ biến trong danh mục',
            ];
        }

        $items = [];
        foreach ($fallbackIds as $fallbackId) {
            if (!isset($productMap[$fallbackId])) {
                continue;
            }

            $items[] = $productMap[$fallbackId];
            if (count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }

    private function getBestSellerProductIdsByCategory(?int $categoryId, int $limit): array
    {
        $connection = $this->resourceConnection->getConnection();
        $salesOrderTable = $this->resourceConnection->getTableName('sales_order');
        $salesOrderItemTable = $this->resourceConnection->getTableName('sales_order_item');
        $categoryProductTable = $this->resourceConnection->getTableName('catalog_category_product');

        $select = $connection->select()
            ->from(['soi' => $salesOrderItemTable], ['product_id'])
            ->joinInner(
                ['so' => $salesOrderTable],
                'so.entity_id = soi.order_id AND so.state <> "canceled"',
                []
            )
            ->where('soi.parent_item_id IS NULL')
            ->where('soi.product_id IS NOT NULL')
            ->group('soi.product_id')
            ->order('SUM(soi.qty_ordered) DESC')
            ->limit($limit);

        if (($categoryId ?? 0) > 0) {
            $select->joinInner(
                ['ccp' => $categoryProductTable],
                'ccp.product_id = soi.product_id AND ccp.category_id = ' . (int) $categoryId,
                []
            );
        }

        return array_map('intval', $connection->fetchCol($select));
    }

    private function getNewestProductIdsByCategory(?int $categoryId, int $limit): array
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect(['entity_id', 'created_at']);
        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        $collection->setVisibility($this->productVisibility->getVisibleInSiteIds());
        $collection->setOrder('created_at', 'DESC');
        $collection->setPageSize($limit);
        $collection->setCurPage(1);

        if (($categoryId ?? 0) > 0) {
            $collection->getSelect()->joinInner(
                ['fs_category_filter' => $this->resourceConnection->getTableName('catalog_category_product')],
                'fs_category_filter.product_id = e.entity_id AND fs_category_filter.category_id = ' . (int) $categoryId,
                []
            )->group('e.entity_id');
        }

        return array_map(
            static fn($product) => (int) $product->getId(),
            $collection->getItems()
        );
    }

    public function getCacheLifetime()
    {
        return null;
    }

    private function getProductImageUrl(\Magento\Catalog\Model\Product $product): string
    {
        $imageFile = $product->getData('small_image')
            ?: $product->getData('image')
            ?: $product->getData('thumbnail');

        if (!$imageFile || $imageFile === 'no_selection') {
            return '';
        }

        return (string) $this->imageHelper
            ->init($product, 'category_page_grid')
            ->setImageFile($imageFile)
            ->getUrl();
    }
}