<?php

namespace FashionStore\Recommendation\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_ENABLED = 'fashionstore_recommendation/general/enabled';
    private const XML_PATH_SERVICE_URL = 'fashionstore_recommendation/general/service_url';
    private const XML_PATH_TOP_K = 'fashionstore_recommendation/general/top_k';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    public function getServiceUrl(): string
    {
        $url = (string) $this->scopeConfig->getValue(self::XML_PATH_SERVICE_URL, ScopeInterface::SCOPE_STORE);

        return rtrim($url, '/');
    }

    public function getTopK(): int
    {
        $topK = (int) $this->scopeConfig->getValue(self::XML_PATH_TOP_K, ScopeInterface::SCOPE_STORE);

        return $topK > 0 ? $topK : 6;
    }
}