<?php
declare(strict_types=1);

namespace Vnpay\Payment\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const PAYMENT_CODE = 'vnpay';
    public const XML_PATH_ACTIVE = 'payment/vnpay/active';
    public const XML_PATH_TITLE = 'payment/vnpay/title';
    public const XML_PATH_TMN_CODE = 'payment/vnpay/tmn_code';
    public const XML_PATH_HASH_SECRET = 'payment/vnpay/hash_secret';
    public const XML_PATH_PAYMENT_URL = 'payment/vnpay/payment_url';
    public const XML_PATH_FORCE_QR = 'payment/vnpay/force_qr';

    private ScopeConfigInterface $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function getTmnCode(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_TMN_CODE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getHashSecret(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_HASH_SECRET, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getPaymentUrl(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_PAYMENT_URL, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isForceQr(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_FORCE_QR, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
