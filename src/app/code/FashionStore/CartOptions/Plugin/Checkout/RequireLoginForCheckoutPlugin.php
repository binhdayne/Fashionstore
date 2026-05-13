<?php

declare(strict_types=1);

namespace FashionStore\CartOptions\Plugin\Checkout;

use Magento\Checkout\Controller\Index\Index;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;

class RequireLoginForCheckoutPlugin
{
    private CustomerSession $customerSession;

    private RedirectFactory $redirectFactory;

    private ManagerInterface $messageManager;

    private UrlInterface $urlBuilder;

    public function __construct(
        CustomerSession $customerSession,
        RedirectFactory $redirectFactory,
        ManagerInterface $messageManager,
        UrlInterface $urlBuilder
    ) {
        $this->customerSession = $customerSession;
        $this->redirectFactory = $redirectFactory;
        $this->messageManager = $messageManager;
        $this->urlBuilder = $urlBuilder;
    }

    public function aroundExecute(Index $subject, callable $proceed)
    {
        if ($this->customerSession->isLoggedIn()) {
            return $proceed();
        }

        $refererUrl = (string) $subject->getRequest()->getServer('HTTP_REFERER');
        $targetUrl = $this->resolveTargetUrl($refererUrl);
        $this->customerSession->setBeforeAuthUrl($targetUrl);
        $this->customerSession->setAfterAuthUrl($targetUrl);

        $this->messageManager->addErrorMessage(__('Vui lòng đăng nhập để tiếp tục thanh toán.'));

        /** @var Redirect $redirect */
        $redirect = $this->redirectFactory->create();

        return $redirect->setPath('customer/account/login');
    }

    private function resolveTargetUrl(string $refererUrl): string
    {
        $baseUrl = $this->urlBuilder->getBaseUrl();

        if ($refererUrl !== '' && str_starts_with($refererUrl, $baseUrl)) {
            return $refererUrl;
        }

        return $baseUrl;
    }
}
