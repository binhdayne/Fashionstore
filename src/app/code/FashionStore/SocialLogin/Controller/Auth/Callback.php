<?php
namespace FashionStore\SocialLogin\Controller\Auth;

use FashionStore\SocialLogin\Model\Config;
use FashionStore\SocialLogin\Model\OAuth\ProviderResolver;
use FashionStore\SocialLogin\Model\SocialCustomerManager;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect as ResultRedirect;
use Magento\Framework\Exception\LocalizedException;

class Callback extends Action implements HttpGetActionInterface
{
    private const SESSION_STATE_PREFIX = 'fashionstore_social_login_state_';

    /**
     * @var ProviderResolver
     */
    private $providerResolver;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var SocialCustomerManager
     */
    private $socialCustomerManager;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    public function __construct(
        Context $context,
        ProviderResolver $providerResolver,
        Config $config,
        SocialCustomerManager $socialCustomerManager,
        CustomerSession $customerSession
    ) {
        parent::__construct($context);
        $this->providerResolver = $providerResolver;
        $this->config = $config;
        $this->socialCustomerManager = $socialCustomerManager;
        $this->customerSession = $customerSession;
    }

    public function execute(): ResultRedirect
    {
        $providerCode = (string) $this->getRequest()->getParam('provider');
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            if (!$this->config->isProviderEnabled($providerCode)) {
                throw new LocalizedException(__('This social login provider is not available.'));
            }

            $this->validateState($providerCode, (string) $this->getRequest()->getParam('state'));

            $oauthError = (string) $this->getRequest()->getParam('error');
            if ($oauthError !== '') {
                throw new LocalizedException(__('Social login was cancelled or denied.'));
            }

            $code = (string) $this->getRequest()->getParam('code');
            if ($code === '') {
                throw new LocalizedException(__('Missing authorization code from the social provider.'));
            }

            $provider = $this->providerResolver->resolve($providerCode);
            $profile = $provider->fetchUserProfile($code);
            $customer = $this->socialCustomerManager->resolveCustomer(
                $providerCode,
                $profile,
                $this->customerSession->isLoggedIn() ? $this->customerSession->getCustomerData() : null
            );

            $this->customerSession->setCustomerDataAsLoggedIn($customer);
            $this->customerSession->regenerateId();

            $this->messageManager->addSuccessMessage(__('You are now signed in with %1.', $this->config->getProviderLabel($providerCode)));

            return $resultRedirect->setPath('customer/account');
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage(__('Unable to complete social login right now.'));
        }

        return $resultRedirect->setPath('customer/account/login');
    }

    private function validateState(string $providerCode, string $state): void
    {
        $sessionKey = self::SESSION_STATE_PREFIX . $providerCode;
        $expectedState = (string) $this->customerSession->getData($sessionKey);
        $this->customerSession->unsetData($sessionKey);

        if ($state === '' || $expectedState === '' || !hash_equals($expectedState, $state)) {
            throw new LocalizedException(__('The social login request is invalid or expired. Please try again.'));
        }
    }
}