<?php
namespace FashionStore\SocialLogin\Model\OAuth;

use Magento\Framework\Exception\LocalizedException;

class ProviderResolver
{
    /**
     * @var GoogleProvider
     */
    private $googleProvider;

    /**
     * @var FacebookProvider
     */
    private $facebookProvider;

    public function __construct(
        GoogleProvider $googleProvider,
        FacebookProvider $facebookProvider
    ) {
        $this->googleProvider = $googleProvider;
        $this->facebookProvider = $facebookProvider;
    }

    public function resolve(string $provider): ProviderInterface
    {
        switch ($provider) {
            case 'google':
                return $this->googleProvider;

            case 'facebook':
                return $this->facebookProvider;

            default:
                throw new LocalizedException(__('Unsupported social login provider.'));
        }
    }
}