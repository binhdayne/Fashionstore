<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Model\Zalopay;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_PREFIX = 'payment/fashionstore_zalopay/';
    private const SAMPLE_APP_ID = 15847;
    private const SAMPLE_KEY1 = '0U93tRzdWEkMLVNYH90aBu5ca0Psql8T';
    private const SAMPLE_KEY2 = 'PurTcToVhvUt7vR2jO6He4lh3nfNEiks';
    private const SAMPLE_SANDBOX_BASE_URL = 'https://sb-openapi.zalopay.vn';

    private ScopeConfigInterface $scopeConfig;

    private UrlInterface $urlBuilder;

    private EncryptorInterface $encryptor;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        UrlInterface $urlBuilder,
        ?EncryptorInterface $encryptor = null
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->urlBuilder = $urlBuilder;
        $this->encryptor = $encryptor ?? ObjectManager::getInstance()->get(EncryptorInterface::class);
    }

    public function isActive(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_PREFIX . 'active', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getAppId(?int $storeId = null): int
    {
        $value = (int) $this->getValue('app_id', $storeId);

        return $value > 0 ? $value : self::SAMPLE_APP_ID;
    }

    public function getKey1(?int $storeId = null): string
    {
        return $this->getSecretValue('key1', self::SAMPLE_KEY1, $storeId);
    }

    public function getKey2(?int $storeId = null): string
    {
        return $this->getSecretValue('key2', self::SAMPLE_KEY2, $storeId);
    }

    public function getBaseUrl(?int $storeId = null): string
    {
        $path = $this->isProductionMode($storeId) ? 'production_base_url' : 'sandbox_base_url';
        $value = rtrim((string) $this->getValue($path, $storeId), '/');

        return $value !== '' ? $value : self::SAMPLE_SANDBOX_BASE_URL;
    }

    public function isProductionMode(?int $storeId = null): bool
    {
        return (string) $this->getValue('environment', $storeId) === 'production';
    }

    public function isSampleSandboxConfig(?int $storeId = null): bool
    {
        return !$this->isProductionMode($storeId)
            && $this->getAppId($storeId) === self::SAMPLE_APP_ID;
    }

    public function getCreateEndpoint(?int $storeId = null): string
    {
        return $this->getBaseUrl($storeId) . '/v2/create';
    }

    public function getQueryEndpoint(?int $storeId = null): string
    {
        return $this->getBaseUrl($storeId) . '/v2/query';
    }

    public function getExpireDurationSeconds(?int $storeId = null): int
    {
        $seconds = (int) $this->getValue('expire_duration_seconds', $storeId);

        return $seconds > 0 ? $seconds : 900;
    }

    public function getPreferredPaymentMethod(?int $storeId = null): string
    {
        return trim((string) $this->getValue('preferred_payment_method', $storeId));
    }

    public function getCallbackUrl(?int $storeId = null): string
    {
        $configuredValue = trim((string) $this->getValue('callback_url', $storeId));

        return $configuredValue !== ''
            ? $configuredValue
            : $this->urlBuilder->getUrl('fashionstore_cartoptions/zalopay/callback', ['_secure' => true]);
    }

    public function getRedirectUrl(?int $storeId = null): string
    {
        $configuredValue = trim((string) $this->getValue('redirect_url', $storeId));

        return $configuredValue !== ''
            ? $configuredValue
            : $this->urlBuilder->getUrl('checkout/onepage/success', ['_secure' => true]);
    }

    public function isConfigured(?int $storeId = null): bool
    {
        return $this->isActive($storeId)
            && $this->getAppId($storeId) > 0
            && $this->getKey1($storeId) !== ''
            && $this->getKey2($storeId) !== '';
    }

    private function getValue(string $field, ?int $storeId = null): mixed
    {
        return $this->scopeConfig->getValue(self::XML_PATH_PREFIX . $field, ScopeInterface::SCOPE_STORE, $storeId);
    }

    private function getSecretValue(string $field, string $defaultValue, ?int $storeId = null): string
    {
        $value = trim((string) $this->getValue($field, $storeId));
        if ($value === '') {
            return $defaultValue;
        }

        if (preg_match('/^\*+$/', $value)) {
            return $this->getAppId($storeId) === self::SAMPLE_APP_ID ? $defaultValue : '';
        }

        $decryptedValue = trim((string) $this->encryptor->decrypt($value));

        return $decryptedValue !== '' ? $decryptedValue : $value;
    }
}
