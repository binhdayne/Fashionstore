<?php

namespace FashionStore\Recommendation\Block;

use FashionStore\Recommendation\Model\Config;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Model\Visitor;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;

class Recommendations extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly CustomerSession $customerSession,
        private readonly Visitor $visitor,
        private readonly Registry $registry,
        private readonly Config $config,
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

    public function getCacheLifetime()
    {
        return null;
    }
}