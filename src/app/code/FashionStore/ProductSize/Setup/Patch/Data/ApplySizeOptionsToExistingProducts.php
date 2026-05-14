<?php

declare(strict_types=1);

namespace FashionStore\ProductSize\Setup\Patch\Data;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
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

    /** @var State */
    private $appState;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        ProductCollectionFactory $productCollectionFactory,
        ProductRepositoryInterface $productRepository,
        State $appState
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productRepository = $productRepository;
        $this->appState = $appState;
    }

    public function apply(): void
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        try {
            $this->ensureAreaCode();

            $productCollection = $this->productCollectionFactory->create();
            $productCollection->addAttributeToSelect(['sku']);
            $productCollection->addFieldToFilter('type_id', ['eq' => 'simple']);

            foreach ($productCollection as $product) {
                $fullProduct = $this->productRepository->getById((int) $product->getId(), false, null, true);
                $this->productRepository->save($fullProduct);
            }
        } finally {
            $this->moduleDataSetup->getConnection()->endSetup();
        }
    }

    private function ensureAreaCode(): void
    {
        try {
            $this->appState->getAreaCode();
        } catch (LocalizedException $exception) {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        }
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
