<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Model\Zalopay;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class ApiClient
{
    private Curl $curl;

    private Json $jsonSerializer;

    private LoggerInterface $logger;

    public function __construct(
        Curl $curl,
        Json $jsonSerializer,
        LoggerInterface $logger
    ) {
        $this->curl = $curl;
        $this->jsonSerializer = $jsonSerializer;
        $this->logger = $logger;
    }

    public function postJson(string $url, array $payload): array
    {
        $this->logger->info('ZaloPay request', [
            'url' => $url,
            'payload' => $payload,
        ]);

        $this->curl->addHeader('Content-Type', 'application/x-www-form-urlencoded');
        $this->curl->addHeader('Accept', 'application/json');
        $this->curl->setTimeout(30);
        $this->curl->post($url, $payload);

        $status = (int) $this->curl->getStatus();
        $body = (string) $this->curl->getBody();

        $this->logger->info('ZaloPay response', [
            'url' => $url,
            'status' => $status,
            'body' => $body,
        ]);

        if ($status < 200 || $status >= 300) {
            throw new LocalizedException(__('ZaloPay API returned HTTP %1.', $status));
        }

        try {
            $decodedBody = $this->jsonSerializer->unserialize($body);
        } catch (\InvalidArgumentException $exception) {
            throw new LocalizedException(__('Unable to decode the ZaloPay response.'));
        }

        if (!is_array($decodedBody)) {
            throw new LocalizedException(__('ZaloPay returned an invalid payload.'));
        }

        return $decodedBody;
    }
}
