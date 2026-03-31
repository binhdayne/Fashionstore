<?php
namespace Fashionstore\Weather\Controller\Ajax;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Catalog\Model\Product\Url as ProductUrl;

class Suggest extends Action
{
    /** @var CollectionFactory */
    private $productCollectionFactory;

    /** @var JsonFactory */
    private $resultJsonFactory;

    /** @var ProductUrl */
    private $productUrl;

    public function __construct(
        Context $context,
        CollectionFactory $productCollectionFactory,
        JsonFactory $resultJsonFactory,
        ProductUrl $productUrl
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->productUrl = $productUrl;
        parent::__construct($context);
    }

    public function execute()
    {
        $query = trim((string)$this->getRequest()->getParam('q', ''));
        $result = $this->resultJsonFactory->create();

        if ($query === '') {
            return $result->setData([]);
        }

        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect('name');
        $collection->addAttributeToFilter('name', ['like' => "%{$query}%"]);
        $collection->setPageSize(8);

        $items = [];
        foreach ($collection as $product) {
            $items[] = [
                'title' => $product->getName(),
                'url' => $this->productUrl->getProductUrl($product),
                'sku' => $product->getSku(),
                'id' => $product->getId(),
            ];
        }

        return $result->setData($items);
    }
}
