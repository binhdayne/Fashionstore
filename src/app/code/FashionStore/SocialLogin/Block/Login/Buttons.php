<?php
namespace FashionStore\SocialLogin\Block\Login;

use FashionStore\SocialLogin\Model\Config;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Buttons extends Template
{
    /**
     * @var Config
     */
    private $config;

    public function __construct(
        Context $context,
        Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->config = $config;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function getEnabledProviders(): array
    {
        $providers = [];

        foreach ($this->config->getSupportedProviders() as $providerCode => $label) {
            if (!$this->config->isProviderVisible($providerCode)) {
                continue;
            }

            $providers[] = [
                'code' => $providerCode,
                'label' => $label,
                'is_configured' => $this->config->isProviderEnabled($providerCode) ? '1' : '0',
                'url' => $this->getUrl('sociallogin/auth/redirect', ['provider' => $providerCode, '_nosid' => true]),
            ];
        }

        return $providers;
    }
}