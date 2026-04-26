<?php
declare(strict_types=1);

namespace FashionStore\ContactWidget\Block;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\ScopeInterface;

class Widget extends Template
{
    private const XML_PATH_ENABLED = 'FashionStore_contactwidget/general/enabled';
    private const XML_PATH_HOTLINE = 'FashionStore_contactwidget/general/hotline_number';
    private const XML_PATH_ZALO = 'FashionStore_contactwidget/general/zalo_url';
    private const XML_PATH_MESSENGER = 'FashionStore_contactwidget/general/messenger_url';

    private ScopeConfigInterface $scopeConfig;

    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context, $data);
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function getContactMethods(): array
    {
        $hotlineNumber = $this->sanitizePhoneNumber(
            (string) $this->scopeConfig->getValue(self::XML_PATH_HOTLINE, ScopeInterface::SCOPE_STORE)
        );

        return [
            [
                'code' => 'hotline',
                'label' => (string) __('Hotline'),
                'href' => $hotlineNumber !== '' ? 'tel:' . $hotlineNumber : '#',
                'background' => '#28a745',
            ],
            [
                'code' => 'messenger',
                'label' => (string) __('Messenger'),
                'href' => $this->normalizeUrl((string) $this->scopeConfig->getValue(self::XML_PATH_MESSENGER, ScopeInterface::SCOPE_STORE)),
                'background' => 'linear-gradient(135deg, #1877f2, #26c6ff)',
            ],
            [
                'code' => 'zalo',
                'label' => (string) __('Chat Zalo'),
                'href' => $this->normalizeUrl((string) $this->scopeConfig->getValue(self::XML_PATH_ZALO, ScopeInterface::SCOPE_STORE)),
                'background' => '#0084ff',
            ],
        ];
    }

    public function shouldOpenNewTab(string $code): bool
    {
        return $code !== 'hotline';
    }

    private function sanitizePhoneNumber(string $phoneNumber): string
    {
        return preg_replace('/[^0-9+]/', '', $phoneNumber) ?: '';
    }

    private function normalizeUrl(string $url): string
    {
        $trimmedUrl = trim($url);
        if ($trimmedUrl === '') {
            return '#';
        }

        return $trimmedUrl;
    }
}