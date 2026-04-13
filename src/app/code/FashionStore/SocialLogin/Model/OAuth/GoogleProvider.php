<?php
namespace FashionStore\SocialLogin\Model\OAuth;

use Magento\Framework\Exception\LocalizedException;

class GoogleProvider extends AbstractProvider
{
    public function getCode(): string
    {
        return 'google';
    }

    public function getAuthorizationUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $this->getClientId(),
            'redirect_uri' => $this->getCallbackUrl(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'online',
            'prompt' => 'select_account',
            'state' => $state,
        ]);
    }

    public function fetchUserProfile(string $code): array
    {
        $tokenData = $this->postForm('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => $this->getClientId(),
            'client_secret' => $this->getClientSecret(),
            'redirect_uri' => $this->getCallbackUrl(),
            'grant_type' => 'authorization_code',
        ]);

        $accessToken = (string) ($tokenData['access_token'] ?? '');
        if ($accessToken === '') {
            throw new LocalizedException(__('Google did not return an access token.'));
        }

        $profile = $this->getJson('https://openidconnect.googleapis.com/v1/userinfo', [
            'Authorization' => 'Bearer ' . $accessToken,
        ]);

        $providerUserId = trim((string) ($profile['sub'] ?? ''));
        if ($providerUserId === '') {
            throw new LocalizedException(__('Google did not return a valid account identifier.'));
        }

        if (isset($profile['email_verified']) && !$profile['email_verified']) {
            throw new LocalizedException(__('The Google account email address is not verified.'));
        }

        return [
            'provider_user_id' => $providerUserId,
            'email' => trim((string) ($profile['email'] ?? '')),
            'firstname' => trim((string) ($profile['given_name'] ?? '')),
            'lastname' => trim((string) ($profile['family_name'] ?? '')),
            'name' => trim((string) ($profile['name'] ?? '')),
        ];
    }
}