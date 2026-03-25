<?php
namespace Fashionstore\Weather\Block;

use Magento\Framework\View\Element\Template;
use Magento\Customer\Model\Session;

class Weather extends Template
{
    protected $customerSession;

    public function __construct(
        Template\Context $context,
        Session $customerSession,
        array $data = []
    ) {
        $this->customerSession = $customerSession;
        parent::__construct($context, $data);
    }

    public function getApiKey()
    {
        return "576aeed77ae5f7fc3f1b5d3e2dc64116";
    }

    public function isLoggedIn()
    {
        return $this->customerSession->isLoggedIn();
    }

    public function getCustomerName()
    {
        if ($this->isLoggedIn()) {
            return $this->customerSession->getCustomer()->getFirstname();
        }
        return "Guest";
    }

    /**
     * ❗ QUAN TRỌNG: Tắt cache để tránh lỗi Guest/Login sai
     */
    public function getCacheLifetime()
    {
        return null;
    }

    /**
     * (Optional) Cache theo từng user (an toàn hơn nếu bật lại cache)
     */
    public function getCacheKeyInfo()
    {
        return [
            'WEATHER_BLOCK',
            $this->customerSession->getCustomerId()
        ];
    }
}