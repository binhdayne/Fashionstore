<?php
namespace FashionStore\SocialLogin\Controller\Auth;

use FashionStore\SocialLogin\Model\Config;
use FashionStore\SocialLogin\Model\OAuth\ProviderResolver;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect as ResultRedirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\Random;

class Redirect extends Action implements HttpGetActionInterface
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
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var Random
     */
    private $random;

    public function __construct(
        Context $context,
        ProviderResolver $providerResolver,
        Config $config,
        CustomerSession $customerSession,
        Random $random
    ) {
        parent::__construct($context);
        $this->providerResolver = $providerResolver;
        $this->config = $config;
        $this->customerSession = $customerSession;
        $this->random = $random;
    }

    public function execute(): ResultRedirect
    {
        $providerCode = (string) $this->getRequest()->getParam('provider');
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            if (!$this->config->isProviderEnabled($providerCode)) {
                throw new LocalizedException(__('This social login provider is not available.'));
            }

            $provider = $this->providerResolver->resolve($providerCode);
            $state = $this->random->getRandomString(32);

            $this->customerSession->setData(self::SESSION_STATE_PREFIX . $providerCode, $state);

            return $resultRedirect->setUrl($provider->getAuthorizationUrl($state));
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage(__('Unable to start social login right now.'));
        }

        return $resultRedirect->setPath('customer/account/login');
    }
}