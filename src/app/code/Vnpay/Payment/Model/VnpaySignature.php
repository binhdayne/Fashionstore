<?php
declare(strict_types=1);

namespace Vnpay\Payment\Model;

class VnpaySignature
{
    /**
     * Create VNPAY signature from sorted parameters.
     */
    public function makeSignature(array $params, string $secret): string
    {
        $hashData = $this->buildHashData($params);

        return hash_hmac('sha512', $hashData, $secret);
    }

    /**
     * Verify that received hash matches request parameters.
     */
    public function isValid(array $params, string $receivedHash, string $secret): bool
    {
        $expectedHash = $this->makeSignature($params, $secret);

        return hash_equals($expectedHash, $receivedHash);
    }

    /**
     * Build VNPAY hash payload format: key=value joined by '&'.
     */
    private function buildHashData(array $params): string
    {
        ksort($params);

        $pairs = [];
        foreach ($params as $key => $value) {
            $pairs[] = urlencode((string) $key) . '=' . urlencode((string) $value);
        }

        return implode('&', $pairs);
    }
}
