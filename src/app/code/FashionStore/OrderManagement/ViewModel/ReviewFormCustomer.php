<?php
declare(strict_types=1);

namespace FashionStore\OrderManagement\ViewModel;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class ReviewFormCustomer implements ArgumentInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession
    ) {
    }

    public function getCustomerName(): string
    {
        if (!$this->customerSession->isLoggedIn()) {
            return '';
        }

        $customer = $this->customerSession->getCustomer();
        $name = trim((string) $customer->getName());

        return $name !== '' ? $name : trim((string) $customer->getEmail());
    }
}
