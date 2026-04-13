<?php

declare(strict_types=1);

namespace FashionStore\ProductSize\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\OptionFactory;
use Magento\Catalog\Model\Product\Option\ValueFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\App\ResourceConnection;

class SizeOptionApplier
{
    private const SIZE_OPTION_TITLES = ['size', 'kich thuoc', 'kích thước'];

    private const CLOTHING_SIZES = ['S', 'M', 'L', 'XL', 'XXL'];

    private const SHOE_SIZES = ['35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45'];

    private const BAG_KEYWORDS = ['tui', 'túi', 'bag', 'backpack', 'wallet'];

    private const SHOE_KEYWORDS = ['giay', 'giày', 'shoe', 'sneaker', 'boot', 'loafer', 'sandal'];

    private const CLOTHING_KEYWORDS = [
        'ao', 'áo', 'quan', 'quần', 'vay', 'váy', 'dam', 'đầm',
        'shirt', 'tee', 'jacket', 'hoodie', 'tank', 'dress', 'skirt', 'clothing', 'apparel'
    ];

    /** @var CategoryCollectionFactory */
    private $categoryCollectionFactory;

    /** @var OptionFactory */
    private $optionFactory;

    /** @var ValueFactory */
    private $valueFactory;

    /** @var ResourceConnection */
    private $resourceConnection;

    public function __construct(
        CategoryCollectionFactory $categoryCollectionFactory,
        OptionFactory $optionFactory,
        ValueFactory $valueFactory,
        ResourceConnection $resourceConnection
    ) {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->optionFactory = $optionFactory;
        $this->valueFactory = $valueFactory;
        $this->resourceConnection = $resourceConnection;
    }

    public function apply(ProductInterface $product): void
    {
        if (!$product instanceof Product || !$product->getId()) {
            return;
        }

        $targetSizes = $this->resolveSizesByCategory($product);

        // Keep only non-size options in-memory to avoid stale option references during product save.
        $currentOptions = (array) $product->getOptions();
        $filteredOptions = [];

        foreach ($currentOptions as $currentOption) {
            $title = mb_strtolower(trim((string) $currentOption->getTitle()));
            if (!in_array($title, self::SIZE_OPTION_TITLES, true)) {
                $filteredOptions[] = $currentOption;
            }
        }

        $product->setOptions($filteredOptions);
        $removedCount = $this->removeSizeOptionsFromDb((int) $product->getId());

        if ($targetSizes === []) {
            if ($removedCount > 0 && count($filteredOptions) === 0) {
                $product->setHasOptions(false);
                $product->setRequiredOptions(false);
            }
            return;
        }

        $sizeOption = $this->optionFactory->create();
        $sizeOption->setData([
            'product_id' => (int) $product->getId(),
            'type' => 'drop_down',
            'is_require' => 1,
            'sort_order' => 10,
            'title' => 'Size',
        ]);

        $values = [];
        foreach ($targetSizes as $index => $size) {
            $values[] = $this->valueFactory->create()->setData([
                'title' => $size,
                'price' => 0,
                'price_type' => 'fixed',
                'sku' => 'size-' . strtolower($size),
                'sort_order' => $index + 1,
            ]);
        }

        $sizeOption->setValues($values);

        $product->setCanSaveCustomOptions(true);
        $product->addOption($sizeOption);
        $product->setHasOptions(true);
        $product->setRequiredOptions(true);
    }

    private function resolveSizesByCategory(Product $product): array
    {
        $categoryIds = array_map('intval', (array) $product->getCategoryIds());
        if ($categoryIds === []) {
            $categoryIds = $this->fetchCategoryIdsFromDb((int) $product->getId());
        }

        if ($categoryIds === []) {
            return [];
        }

        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect(['name', 'url_key']);
        $collection->addIdFilter($categoryIds);

        $matchedBag = false;
        $matchedShoe = false;
        $matchedClothing = false;

        foreach ($collection as $category) {
            $name = mb_strtolower((string) $category->getName());
            $urlKey = mb_strtolower((string) $category->getData('url_key'));
            $haystack = $name . ' ' . $urlKey;

            if ($this->containsAnyKeyword($haystack, self::BAG_KEYWORDS)) {
                $matchedBag = true;
            }

            if ($this->containsAnyKeyword($haystack, self::SHOE_KEYWORDS)) {
                $matchedShoe = true;
            }

            if ($this->containsAnyKeyword($haystack, self::CLOTHING_KEYWORDS)) {
                $matchedClothing = true;
            }
        }

        // Clothing (ao/quan/vay/dam) must always keep size selection as requested.
        if ($matchedClothing) {
            return self::CLOTHING_SIZES;
        }

        if ($matchedShoe) {
            return self::SHOE_SIZES;
        }

        if ($matchedBag) {
            return [];
        }

        return [];
    }

    private function fetchCategoryIdsFromDb(int $productId): array
    {
        if ($productId <= 0) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('catalog_category_product');

        $ids = $connection->fetchCol(
            $connection->select()
                ->from($table, ['category_id'])
                ->where('product_id = ?', $productId)
        );

        return array_map('intval', $ids);
    }

    private function removeSizeOptionsFromDb(int $productId): int
    {
        if ($productId <= 0) {
            return 0;
        }

        $connection = $this->resourceConnection->getConnection();
        $optionTable = $this->resourceConnection->getTableName('catalog_product_option');
        $optionTitleTable = $this->resourceConnection->getTableName('catalog_product_option_title');
        $optionPriceTable = $this->resourceConnection->getTableName('catalog_product_option_price');
        $optionTypeValueTable = $this->resourceConnection->getTableName('catalog_product_option_type_value');
        $optionTypeTitleTable = $this->resourceConnection->getTableName('catalog_product_option_type_title');
        $optionTypePriceTable = $this->resourceConnection->getTableName('catalog_product_option_type_price');

        $optionIds = $connection->fetchCol(
            $connection->select()
                ->from(['o' => $optionTable], ['option_id'])
                ->joinLeft(['ot' => $optionTitleTable], 'ot.option_id = o.option_id', [])
                ->where('o.product_id = ?', $productId)
                ->where('LOWER(ot.title) IN (?)', self::SIZE_OPTION_TITLES)
        );

        if ($optionIds === []) {
            return 0;
        }

        $connection->delete($optionTypeTitleTable, ['option_type_id IN (?)' => $connection->select()
            ->from($optionTypeValueTable, ['option_type_id'])
            ->where('option_id IN (?)', $optionIds)]);

        $connection->delete($optionTypePriceTable, ['option_type_id IN (?)' => $connection->select()
            ->from($optionTypeValueTable, ['option_type_id'])
            ->where('option_id IN (?)', $optionIds)]);

        $connection->delete($optionTypeValueTable, ['option_id IN (?)' => $optionIds]);
        $connection->delete($optionPriceTable, ['option_id IN (?)' => $optionIds]);
        $connection->delete($optionTitleTable, ['option_id IN (?)' => $optionIds]);
        $connection->delete($optionTable, ['option_id IN (?)' => $optionIds]);

        return count($optionIds);
    }

    private function containsAnyKeyword(string $haystack, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($haystack, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
