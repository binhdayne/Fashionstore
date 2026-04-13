<?php
namespace FashionStore\SocialLogin\Model\OAuth;

use FashionStore\SocialLogin\Model\Config;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\UrlInterface;

abstract class AbstractProvider implements ProviderInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var CurlFactory
     */
    private $curlFactory;

    public function __construct(
        Config $config,
        UrlInterface $urlBuilder,
        CurlFactory $curlFactory
    ) {
        $this->config = $config;
        $this->urlBuilder = $urlBuilder;
        $this->curlFactory = $curlFactory;
    }

    protected function getClientId(): string
    {
        return $this->config->getClientId($this->getCode());
    }

    protected function getClientSecret(): string
    {
        return $this->config->getClientSecret($this->getCode());
    }

    protected function getCallbackUrl(): string
    {
        return $this->urlBuilder->getUrl('sociallogin/auth/callback', ['provider' => $this->getCode(), '_nosid' => true]);
    }

    /**
     * @param array<string, scalar> $payload
     * @return array<string, mixed>
     */
    protected function postForm(string $url, array $payload): array
    {
        $curl = $this->curlFactory->create();
        $curl->setOption(CURLOPT_TIMEOUT, 30);
        $curl->post($url, $payload);

        return $this->decodeResponse($curl->getStatus(), $curl->getBody());
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    protected function getJson(string $url, array $headers = []): array
    {
        $curl = $this->curlFactory->create();
        $curl->setOption(CURLOPT_TIMEOUT, 30);

        if ($headers !== []) {
            $curl->setHeaders($headers);
        }

        $curl->get($url);

        return $this->decodeResponse($curl->getStatus(), $curl->getBody());
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(int $status, string $body): array
    {
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new LocalizedException(__('The social provider returned an invalid response.'));
        }

        if ($status >= 400) {
            $message = (string) ($data['error_description'] ?? $data['message'] ?? __('The social provider request failed.'));
            throw new LocalizedException(__($message));
        }

        return $data;
    }
}