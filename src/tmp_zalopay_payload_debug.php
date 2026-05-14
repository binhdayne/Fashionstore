<?php
declare(strict_types=1);

$appId = 15847;
$key1 = '0U93tRzdWEkMLVNYH90aBu5ca0Psql8T';
$endpoint = 'https://sb-openapi.zalopay.vn/v2/create';
$basePayload = [
    'app_id' => $appId,
    'app_user' => 'debug@example.com',
    'app_time' => (int) round(microtime(true) * 1000),
    'amount' => 699005,
    'description' => 'Debug ZaloPay payload',
    'callback_url' => 'http://localhost/fashionstore_cartoptions/zalopay/callback/',
    'bank_code' => '',
    'item' => json_encode([
        [
            'itemid' => 'debug-sku',
            'itemname' => 'Debug Item',
            'itemprice' => 699005,
            'itemquantity' => 1,
        ],
    ], JSON_UNESCAPED_UNICODE),
];

$cases = [
    'with_vietqr' => [
        'embed_data' => json_encode([
            'redirecturl' => 'http://localhost/checkout/onepage/success/',
            'merchantinfo' => 'debug-vietqr',
            'preferred_payment_method' => ['vietqr'],
        ], JSON_UNESCAPED_UNICODE),
    ],
    'without_preferred_method' => [
        'embed_data' => json_encode([
            'redirecturl' => 'http://localhost/checkout/onepage/success/',
            'merchantinfo' => 'debug-no-pm',
        ], JSON_UNESCAPED_UNICODE),
    ],
];

foreach ($cases as $label => $override) {
    $payload = $basePayload;
    $payload['app_trans_id'] = date('ymd') . '_' . $label . '_' . substr((string) time(), -5);
    $payload = array_merge($payload, $override);
    $payload['mac'] = hash_hmac('sha256', implode('|', [
        (string) $payload['app_id'],
        (string) $payload['app_trans_id'],
        (string) $payload['app_user'],
        (string) $payload['amount'],
        (string) $payload['app_time'],
        (string) $payload['embed_data'],
        (string) $payload['item'],
    ]), $key1);

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);

    $body = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "=== {$label} ===\n";
    echo json_encode([
        'payload' => $payload,
        'status' => $status,
        'curl_error' => $error,
        'response' => json_decode((string) $body, true) ?: $body,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "\n\n";
}