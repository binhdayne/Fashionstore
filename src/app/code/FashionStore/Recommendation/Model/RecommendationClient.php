<?php

namespace FashionStore\Recommendation\Model;

use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;

class RecommendationClient
{
    public function __construct(
        private readonly Curl $curl,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->config->isEnabled() && $this->config->getServiceUrl() !== '';
    }

    public function fetchRecommendations(
        string $userId,
        ?int $productId = null,
        ?int $categoryId = null,
        ?int $topK = null
    ): array {
        if (!$this->isEnabled()) {
            return [
                'mode' => 'disabled',
                'items' => [],
            ];
        }

        $query = [
            'top_k' => $topK ?: $this->config->getTopK(),
        ];

        if ($productId) {
            $query['seed_product_id'] = $productId;
        }

        if ($categoryId) {
            $query['category_id'] = $categoryId;
        }

        $url = $this->config->getServiceUrl()
            . '/recommendations/'
            . rawurlencode($userId)
            . '?' . http_build_query($query);

        try {
            $this->curl->setOption(CURLOPT_CONNECTTIMEOUT, 3);
            $this->curl->setOption(CURLOPT_TIMEOUT, 10);
            $this->curl->addHeader('Accept', 'application/json');
            $this->curl->get($url);

            if ($this->curl->getStatus() >= 400) {
                throw new \RuntimeException('Recommendation service returned HTTP ' . $this->curl->getStatus());
            }

            $payload = json_decode($this->curl->getBody(), true);

            return is_array($payload) ? $payload : ['mode' => 'invalid', 'items' => []];
        } catch (\Throwable $exception) {
            $this->logger->warning('Recommendation request failed', [
                'message' => $exception->getMessage(),
                'user_id' => $userId,
            ]);

            return [
                'mode' => 'error',
                'items' => [],
                'error' => $exception->getMessage(),
            ];
        }
    }
}