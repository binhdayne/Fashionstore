<?php

namespace FashionStore\Recommendation\Controller\Ajax;

use FashionStore\Recommendation\Model\Config;
use FashionStore\Recommendation\Model\RecommendationClient;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Pricing\PriceCurrencyInterface;

class Index extends Action implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly RecommendationClient $recommendationClient,
        private readonly CollectionFactory $productCollectionFactory,
        private readonly Visibility $productVisibility,
        private readonly ImageHelper $imageHelper,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly Config $config
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        if (!$this->config->isEnabled()) {
            return $result->setData([
                'mode' => 'disabled',
                'items' => [],
            ]);
        }

        $userId = trim((string) $this->getRequest()->getParam('user_id', 'guest'));
        $productId = (int) $this->getRequest()->getParam('product_id', 0);
        $categoryId = (int) $this->getRequest()->getParam('category_id', 0);
        $topK = (int) $this->getRequest()->getParam('top_k', $this->config->getTopK());

        $servicePayload = $this->recommendationClient->fetchRecommendations(
            $userId !== '' ? $userId : 'guest',
            $productId ?: null,
            $categoryId ?: null,
            $topK
        );

        $recommendedItems = $servicePayload['items'] ?? [];
        $productIds = [];
        foreach ($recommendedItems as $item) {
            $candidateId = (int) ($item['product_id'] ?? 0);
            if ($candidateId > 0) {
                $productIds[] = $candidateId;
            }
        }

        if ($productIds === []) {
            $servicePayload['items'] = [];

            return $result->setData($servicePayload);
        }

        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect(['name', 'price', 'small_image', 'thumbnail', 'url_key']);
        $collection->addIdFilter($productIds);
        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        $collection->setVisibility($this->productVisibility->getVisibleInSiteIds());
        $collection->addUrlRewrite();

        $productMap = [];
        foreach ($collection as $product) {
            $productMap[(int) $product->getId()] = [
                'product_id' => (int) $product->getId(),
                'name' => (string) $product->getName(),
                'sku' => (string) $product->getSku(),
                'url' => (string) $product->getProductUrl(),
                'image' => (string) $this->imageHelper->init($product, 'category_page_grid')->getUrl(),
                'price' => (float) $product->getFinalPrice(),
                'price_html' => $this->priceCurrency->convertAndFormat((float) $product->getFinalPrice()),
            ];
        }

        $hydratedItems = [];
        foreach ($recommendedItems as $item) {
            $candidateId = (int) ($item['product_id'] ?? 0);
            if (!isset($productMap[$candidateId])) {
                continue;
            }

            $hydratedItems[] = array_merge($productMap[$candidateId], [
                'score' => round((float) ($item['score'] ?? 0.0), 4),
                'reason' => (string) ($item['reason'] ?? ''),
            ]);
        }

        $servicePayload['items'] = $hydratedItems;

        return $result->setData($servicePayload);
    }
}