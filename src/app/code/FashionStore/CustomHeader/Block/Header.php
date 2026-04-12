<?php

namespace FashionStore\CustomHeader\Block;

use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Search\Helper\Data as SearchHelper;
use Magento\Search\Model\ResourceModel\Query\CollectionFactory as QueryCollectionFactory;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\View\Element\Template;

class Header extends Template
{
    protected $customerSession;

    protected $searchHelper;

    protected $queryCollectionFactory;

    protected $productCollectionFactory;

    protected $productVisibility;

    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        SearchHelper $searchHelper,
        QueryCollectionFactory $queryCollectionFactory,
        ProductCollectionFactory $productCollectionFactory,
        Visibility $productVisibility,
        array $data = []
    ) {
        $this->customerSession = $customerSession;
        $this->searchHelper = $searchHelper;
        $this->queryCollectionFactory = $queryCollectionFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productVisibility = $productVisibility;
        parent::__construct($context, $data);
    }

    public function isLoggedIn()
    {
        return $this->customerSession->isLoggedIn();
    }

    public function getCustomerName()
    {
        if ($this->isLoggedIn()) {
            return $this->customerSession->getCustomer()->getName();
        }

        return 'Khach';
    }

    public function getBaseUrl()
    {
        return $this->_storeManager->getStore()->getBaseUrl();
    }

    public function getSuggestProductsUrl()
    {
        return $this->getUrl('customheader/search/suggest');
    }

    public function getWeatherUrl()
    {
        return $this->getUrl('customheader/ajax/weather');
    }

    public function getNewsUrl()
    {
        return $this->getUrl('customheader/ajax/news');
    }

    public function getMaxQueryLength()
    {
        return (int) $this->searchHelper->getMaxQueryLength();
    }

    public function getPopularSearchTerms($limit = 3)
    {
        $terms = [];
        $storeId = (int) $this->_storeManager->getStore()->getId();
        $collection = $this->queryCollectionFactory->create();

        $collection->setStoreId($storeId);
        $collection->setPopularQueryFilter($storeId);
        $collection->setPageSize(max(10, (int) $limit));
        $collection->setCurPage(1);

        foreach ($collection as $query) {
            $term = trim((string) $query->getQueryText());

            if ($term === '' || in_array($term, $terms, true)) {
                continue;
            }

            $terms[] = $term;

            if (count($terms) >= $limit) {
                break;
            }
        }

        if (count($terms) < $limit) {
            foreach ($this->getFallbackProductNames($limit) as $productName) {
                if (in_array($productName, $terms, true)) {
                    continue;
                }

                $terms[] = $productName;

                if (count($terms) >= $limit) {
                    break;
                }
            }
        }

        return array_slice($terms, 0, $limit);
    }

    private function getFallbackProductNames($limit)
    {
        $names = [];
        $storeId = (int) $this->_storeManager->getStore()->getId();
        $collection = $this->productCollectionFactory->create();

        $collection->setStoreId($storeId);
        $collection->addStoreFilter($storeId);
        $collection->addAttributeToSelect(['name']);
        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        $collection->setVisibility($this->productVisibility->getVisibleInSiteIds());
        $collection->addAttributeToSort('entity_id', 'DESC');
        $collection->setPageSize((int) $limit);
        $collection->setCurPage(1);

        foreach ($collection as $product) {
            $name = trim((string) $product->getName());

            if ($name === '') {
                continue;
            }

            $names[] = $name;
        }

        return $names;
    }
}
