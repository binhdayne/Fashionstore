<?php
declare(strict_types=1);

namespace Vnpay\Payment\Model\Payment;

use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\UrlInterface;
use Magento\Payment\Model\Method\AbstractMethod;
use Vnpay\Payment\Model\Config;

class Vnpay extends AbstractMethod
{
    protected $_code = Config::PAYMENT_CODE;
    protected $_isOffline = false;

    protected $_canAuthorize = false;
    protected $_canCapture = false;
    protected $_canVoid = false;
    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;

    private UrlInterface $urlBuilder;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        UrlInterface $urlBuilder,
        ?\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        ?\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = [],
        ?DirectoryHelper $directory = null
    ) {
        $this->urlBuilder = $urlBuilder;

        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data,
            $directory
        );
    }

    /**
     * Redirect customer to VNPAY after place order.
     */
    public function getOrderPlaceRedirectUrl(): string
    {
        return $this->urlBuilder->getUrl('vnpay/payment/redirect', ['_secure' => true]);
    }
}
