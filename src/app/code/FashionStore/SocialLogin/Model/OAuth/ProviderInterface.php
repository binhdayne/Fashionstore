<?php
namespace FashionStore\SocialLogin\Model\OAuth;

interface ProviderInterface
{
    public function getCode(): string;

    public function getAuthorizationUrl(string $state): string;

    /**
     * @return array<string, string>
     */
    public function fetchUserProfile(string $code): array;
}