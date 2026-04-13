<?php

declare(strict_types=1);

namespace FashionStore\ProductSize\Setup\Patch\Data;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class ApplySizeOptionsToExistingProducts implements DataPatchInterface
{
    /** @var ModuleDataSetupInterface */
    private $moduleDataSetup;

    /** @var ProductCollectionFactory */
    private $productCollectionFactory;

    /** @var ProductRepositoryInterface */
    private $productRepository;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        ProductCollectionFactory $productCollectionFactory,
        ProductRepositoryInterface $productRepository
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productRepository = $productRepository;
    }

    public function apply(): void
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $productCollection = $this->productCollectionFactory->create();
        $productCollection->addAttributeToSelect(['sku']);
        $productCollection->addFieldToFilter('type_id', ['eq' => 'simple']);

        foreach ($productCollection as $product) {
            $fullProduct = $this->productRepository->getById((int) $product->getId(), false, null, true);
            $this->productRepository->save($fullProduct);
        }

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
