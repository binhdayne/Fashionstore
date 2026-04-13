<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Model\Zalopay;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;

class ApiClient
{
    private Curl $curl;

    private Json $jsonSerializer;

    public function __construct(
        Curl $curl,
        Json $jsonSerializer
    ) {
        $this->curl = $curl;
        $this->jsonSerializer = $jsonSerializer;
    }

    public function postJson(string $url, array $payload): array
    {
        $this->curl->addHeader('Content-Type', 'application/json');
        $this->curl->addHeader('Accept', 'application/json');
        $this->curl->setTimeout(30);
        $this->curl->post($url, $this->jsonSerializer->serialize($payload));

        $status = (int) $this->curl->getStatus();
        $body = (string) $this->curl->getBody();

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