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
            return 'Khách hàng';
        }

        $customer = $this->customerSession->getCustomer();
        $name = trim((string) $customer->getName());

        if ($name !== '') {
            return $name;
        }

        $email = trim((string) $customer->getEmail());

        if ($email !== '' && str_contains($email, '@')) {
            return trim((string) strstr($email, '@', true));
        }

        return $email !== '' ? $email : 'Khách hàng';
    }
}
