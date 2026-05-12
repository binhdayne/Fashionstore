<?php

namespace FashionStore\CustomHeader\Controller\Search;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Store\Model\StoreManagerInterface;

class Suggest extends Action implements HttpGetActionInterface
{
    private $resultJsonFactory;

    private $productCollectionFactory;

    private $storeManager;

    private $productVisibility;

    private $imageHelper;

    private $pricingHelper;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        CollectionFactory $productCollectionFactory,
        StoreManagerInterface $storeManager,
        Visibility $productVisibility,
        ImageHelper $imageHelper,
        PricingHelper $pricingHelper
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->storeManager = $storeManager;
        $this->productVisibility = $productVisibility;
        $this->imageHelper = $imageHelper;
        $this->pricingHelper = $pricingHelper;
        parent::__construct($context);
    }

    public function execute()
    {
        $query = trim((string) $this->getRequest()->getParam('q', ''));
        $result = $this->resultJsonFactory->create();

        if ($query === '') {
            return $result->setData([]);
        }

        $storeId = (int) $this->storeManager->getStore()->getId();
        $likeQuery = '%' . addcslashes($query, '%_\\') . '%';
        $collection = $this->productCollectionFactory->create();

        $collection->setStoreId($storeId);
        $collection->addStoreFilter($storeId);
        $collection->addAttributeToSelect(['name', 'sku', 'small_image', 'thumbnail', 'price']);
        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        $collection->setVisibility($this->productVisibility->getVisibleInSiteIds());
        $collection->addAttributeToFilter(
            [
                ['attribute' => 'name', 'like' => $likeQuery],
                ['attribute' => 'sku', 'like' => $likeQuery],
            ]
        );
        $collection->addUrlRewrite();
        $collection->addAttributeToSort('name', 'ASC');
        $collection->setPageSize(5);
        $collection->setCurPage(1);

        $items = [];

        foreach ($collection as $product) {
            $items[] = [
                'name' => (string) $product->getName(),
                'sku' => (string) $product->getSku(),
                'url' => $product->getProductUrl(),
                'image' => (string) $this->imageHelper->init($product, 'category_page_grid')->getUrl(),
                'price' => (string) $this->pricingHelper->currencyByStore(
                    (float) $product->getFinalPrice(),
                    $storeId,
                    true,
                    false
                ),
            ];
        }

        return $result->setData($items);
    }
}