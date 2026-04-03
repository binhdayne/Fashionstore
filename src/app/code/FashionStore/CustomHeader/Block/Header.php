<?php

namespace FashionStore\CustomHeader\Block;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\View\Element\Template;

class Header extends Template
{
    protected $customerSession;

    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        array $data = []
    ) {
        $this->customerSession = $customerSession;
        parent::__construct($context, $data);
    }

    public function isLoggedIn()
    {
        return $this->customerSession->isLoggedIn();
    }

    public function getCustomerName()
    {
        if ($this->isLoggedIn()) {
            return $this->customerSession->getCustomer()->getName();
        }

        return 'Khach';
    }

    public function getBaseUrl()
    {
        return $this->_storeManager->getStore()->getBaseUrl();
    }
}
