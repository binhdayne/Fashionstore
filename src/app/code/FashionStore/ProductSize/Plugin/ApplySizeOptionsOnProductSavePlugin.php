<?php

declare(strict_types=1);

namespace FashionStore\ProductSize\Plugin;

use FashionStore\ProductSize\Model\SizeOptionApplier;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;

class ApplySizeOptionsOnProductSavePlugin
{
    /** @var SizeOptionApplier */
    private $sizeOptionApplier;

    public function __construct(SizeOptionApplier $sizeOptionApplier)
    {
        $this->sizeOptionApplier = $sizeOptionApplier;
    }

    public function beforeSave(
        ProductRepositoryInterface $subject,
        ProductInterface $product,
        bool $saveOptions = false
    ): array {
        $this->sizeOptionApplier->apply($product);

        return [$product, $saveOptions];
    }
}
