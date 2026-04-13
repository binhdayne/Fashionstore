<?php

declare(strict_types=1);

namespace FashionStore\CartOptions\Plugin\Checkout;

use Magento\Checkout\Controller\Index\Index;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;

class RequireLoginForCheckoutPlugin
{
    private CustomerSession $customerSession;

    private RedirectFactory $redirectFactory;

    private ManagerInterface $messageManager;

    public function __construct(
        CustomerSession $customerSession,
        RedirectFactory $redirectFactory,
        ManagerInterface $messageManager
    ) {
        $this->customerSession = $customerSession;
        $this->redirectFactory = $redirectFactory;
        $this->messageManager = $messageManager;
    }

    public function aroundExecute(Index $subject, callable $proceed)
    {
        if ($this->customerSession->isLoggedIn()) {
            return $proceed();
        }

        $this->messageManager->addErrorMessage(__('Vui lòng đăng nhập để tiếp tục thanh toán.'));

        /** @var Redirect $redirect */
        $redirect = $this->redirectFactory->create();

        return $redirect->setPath('customer/account/login');
    }
}
