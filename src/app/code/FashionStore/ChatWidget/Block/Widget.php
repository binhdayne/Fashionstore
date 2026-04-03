<?php
declare(strict_types=1);

namespace FashionStore\ChatWidget\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Widget extends Template
{
    private const XML_PATH_RAG_URL = 'FashionStore_chatwidget/general/rag_url';
    private const XML_PATH_ENABLED = 'FashionStore_chatwidget/general/enabled';

    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context, $data);
    }

    private ScopeConfigInterface $scopeConfig;

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getRagUrl(): string
    {
        $ragUrl = $this->scopeConfig->getValue(
            self::XML_PATH_RAG_URL,
            ScopeInterface::SCOPE_STORE
        );

        if (!is_string($ragUrl) || trim($ragUrl) === '') {
            return 'http://localhost:8000';
        }

        return $ragUrl;
    }
}
