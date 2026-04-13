<?php
namespace FashionStore\SocialLogin\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_ENABLED = 'fashionstore_social_login/general/enabled';
    private const XML_PATH_GOOGLE_ENABLED = 'fashionstore_social_login/google/enabled';
    private const XML_PATH_GOOGLE_CLIENT_ID = 'fashionstore_social_login/google/client_id';
    private const XML_PATH_GOOGLE_CLIENT_SECRET = 'fashionstore_social_login/google/client_secret';
    private const XML_PATH_FACEBOOK_ENABLED = 'fashionstore_social_login/facebook/enabled';
    private const XML_PATH_FACEBOOK_CLIENT_ID = 'fashionstore_social_login/facebook/client_id';
    private const XML_PATH_FACEBOOK_CLIENT_SECRET = 'fashionstore_social_login/facebook/client_secret';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
    }

    /**
     * @return array<string, string>
     */
    public function getSupportedProviders(): array
    {
        return [
            'google' => 'Google',
            'facebook' => 'Facebook',
        ];
    }

    public function getProviderLabel(string $provider): string
    {
        return $this->getSupportedProviders()[$provider] ?? ucfirst($provider);
    }

    public function isModuleEnabled(?int $storeId = null): bool
    {
        return $this->isSetFlag(self::XML_PATH_ENABLED, $storeId);
    }

    public function isProviderEnabled(string $provider, ?int $storeId = null): bool
    {
        return $this->isProviderVisible($provider, $storeId)
            && $this->getClientId($provider, $storeId) !== ''
            && $this->getClientSecret($provider, $storeId) !== '';
    }

    public function isProviderVisible(string $provider, ?int $storeId = null): bool
    {
        if (!$this->isModuleEnabled($storeId)) {
            return false;
        }

        return $this->isSetFlag($this->getEnabledPath($provider), $storeId);
    }

    public function getClientId(string $provider, ?int $storeId = null): string
    {
        return trim((string) $this->getValue($this->getClientIdPath($provider), $storeId));
    }

    public function getClientSecret(string $provider, ?int $storeId = null): string
    {
        $value = trim((string) $this->getValue($this->getClientSecretPath($provider), $storeId));
        if ($value === '') {
            return '';
        }

        return (string) $this->encryptor->decrypt($value);
    }

    private function isSetFlag(string $path, ?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    private function getValue(string $path, ?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    private function getEnabledPath(string $provider): string
    {
        switch ($provider) {
            case 'google':
                return self::XML_PATH_GOOGLE_ENABLED;

            case 'facebook':
                return self::XML_PATH_FACEBOOK_ENABLED;

            default:
                return '';
        }
    }

    private function getClientIdPath(string $provider): string
    {
        switch ($provider) {
            case 'google':
                return self::XML_PATH_GOOGLE_CLIENT_ID;

            case 'facebook':
                return self::XML_PATH_FACEBOOK_CLIENT_ID;

            default:
                return '';
        }
    }

    private function getClientSecretPath(string $provider): string
    {
        switch ($provider) {
            case 'google':
                return self::XML_PATH_GOOGLE_CLIENT_SECRET;

            case 'facebook':
                return self::XML_PATH_FACEBOOK_CLIENT_SECRET;

            default:
                return '';
        }
    }
}