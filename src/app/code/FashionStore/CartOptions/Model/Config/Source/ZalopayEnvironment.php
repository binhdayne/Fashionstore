<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ZalopayEnvironment implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'sandbox', 'label' => __('Sandbox')],
            ['value' => 'production', 'label' => __('Production')],
        ];
    }
}