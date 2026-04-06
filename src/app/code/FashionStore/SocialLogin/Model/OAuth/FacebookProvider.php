<?php
namespace FashionStore\SocialLogin\Model\OAuth;

use Magento\Framework\Exception\LocalizedException;

class FacebookProvider extends AbstractProvider
{
    public function getCode(): string
    {
        return 'facebook';
    }

    public function getAuthorizationUrl(string $state): string
    {
        return 'https://www.facebook.com/v19.0/dialog/oauth?' . http_build_query([
            'client_id' => $this->getClientId(),
            'redirect_uri' => $this->getCallbackUrl(),
            'response_type' => 'code',
            'scope' => 'email,public_profile',
            'state' => $state,
        ]);
    }

    public function fetchUserProfile(string $code): array
    {
        $tokenData = $this->getJson('https://graph.facebook.com/v19.0/oauth/access_token?' . http_build_query([
            'client_id' => $this->getClientId(),
            'client_secret' => $this->getClientSecret(),
            'redirect_uri' => $this->getCallbackUrl(),
            'code' => $code,
        ]));

        $accessToken = (string) ($tokenData['access_token'] ?? '');
        if ($accessToken === '') {
            throw new LocalizedException(__('Facebook did not return an access token.'));
        }

        $profile = $this->getJson('https://graph.facebook.com/me?' . http_build_query([
            'fields' => 'id,first_name,last_name,name,email',
            'access_token' => $accessToken,
        ]));

        $providerUserId = trim((string) ($profile['id'] ?? ''));
        if ($providerUserId === '') {
            throw new LocalizedException(__('Facebook did not return a valid account identifier.'));
        }

        return [
            'provider_user_id' => $providerUserId,
            'email' => trim((string) ($profile['email'] ?? '')),
            'firstname' => trim((string) ($profile['first_name'] ?? '')),
            'lastname' => trim((string) ($profile['last_name'] ?? '')),
            'name' => trim((string) ($profile['name'] ?? '')),
        ];
    }
}