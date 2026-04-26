<?php
declare(strict_types=1);

function fetchHtml(string $url): ?string
{
    $command = sprintf(
        'curl -L -sS --max-time 20 -A %s %s 2>/dev/null',
        escapeshellarg('PriceFilterVerifier/1.0'),
        escapeshellarg($url)
    );
    $html = shell_exec($command);
    return is_string($html) && $html !== '' ? $html : null;
}

function absoluteUrl(string $baseUrl, string $href): ?string
{
    $href = trim($href);
    if (
        $href === ''
        || str_starts_with($href, '#')
        || str_starts_with($href, 'javascript:')
        || str_starts_with($href, 'mailto:')
    ) {
        return null;
    }

    if (preg_match('/^https?:\/\//i', $href)) {
        return $href;
    }

    $base = parse_url($baseUrl);
    if (!$base || empty($base['scheme']) || empty($base['host'])) {
        return null;
    }

    $schemeHost = $base['scheme'] . '://' . $base['host'];
    if (!empty($base['port'])) {
        $schemeHost .= ':' . $base['port'];
    }

    if (str_starts_with($href, '/')) {
        return $schemeHost . $href;
    }

    $basePath = $base['path'] ?? '/';
    $dir = rtrim(str_replace('\\', '/', dirname($basePath)), '/');
    return $schemeHost . ($dir === '' ? '' : $dir) . '/' . ltrim($href, '/');
}

function extractLinks(string $html, string $baseUrl): array
{
    $doc = new DOMDocument();
    @$doc->loadHTML($html);
    $xpath = new DOMXPath($doc);
    $nodes = $xpath->query('//a[@href]');

    $links = [];
    if ($nodes !== false) {
        foreach ($nodes as $node) {
            $href = $node->getAttribute('href');
            $url = absoluteUrl($baseUrl, $href);
            if ($url !== null) {
                $links[] = $url;
            }
        }
    }

    return array_values(array_unique($links));
}

function extractPriceFilterLinks(string $html, string $baseUrl): array
{
    $doc = new DOMDocument();
    @$doc->loadHTML($html);
    $xpath = new DOMXPath($doc);
    $nodes = $xpath->query('//a[@href]');

    $links = [];
    if ($nodes !== false) {
        foreach ($nodes as $node) {
            $href = $node->getAttribute('href');
            $url = absoluteUrl($baseUrl, $href);
            if ($url !== null && strpos($url, 'price=') !== false) {
                $links[] = $url;
            }
        }
    }

    return array_values(array_unique($links));
}

function parseRange(string $url): ?array
{
    $query = parse_url($url, PHP_URL_QUERY);
    if (!is_string($query) || $query === '') {
        return null;
    }

    parse_str($query, $params);
    if (!isset($params['price']) || !is_string($params['price'])) {
        return null;
    }

    $token = explode(',', $params['price'])[0] ?? '';
    if (!str_contains($token, '-')) {
        return null;
    }

    [$fromRaw, $toRaw] = explode('-', $token, 2);

    return [
        'from' => $fromRaw === '' ? null : (float) $fromRaw,
        'to' => $toRaw === '' ? null : (float) $toRaw,
        'raw' => $token,
    ];
}

function extractRenderedPrices(string $html): array
{
    $doc = new DOMDocument();
    @$doc->loadHTML($html);
    $xpath = new DOMXPath($doc);

    $prices = [];
    $nodes = $xpath->query('//span[contains(@class, "price-wrapper") and @data-price-amount]');
    if ($nodes !== false) {
        foreach ($nodes as $node) {
            $raw = $node->getAttribute('data-price-amount');
            if ($raw !== '' && is_numeric($raw)) {
                $prices[] = (float) $raw;
            }
        }
    }

    if (empty($prices) && preg_match_all('/data-price-amount="([0-9]+(?:\.[0-9]+)?)"/', $html, $matches)) {
        foreach ($matches[1] as $amount) {
            $prices[] = (float) $amount;
        }
    }

    return $prices;
}

function looksLikeCategoryUrl(string $url, string $baseHost): bool
{
    $parts = parse_url($url);
    if (!$parts || ($parts['host'] ?? '') !== $baseHost) {
        return false;
    }

    $path = $parts['path'] ?? '';
    if ($path === '' || $path === '/' || str_ends_with($path, '.css') || str_ends_with($path, '.js')) {
        return false;
    }

    $blocked = ['/customer/', '/checkout/', '/wishlist/', '/search/', '/catalog/product'];
    foreach ($blocked as $segment) {
        if (stripos($url, $segment) !== false) {
            return false;
        }
    }

    return str_ends_with($path, '.html');
}

$baseUrl = 'http://nginx/';
$homeHtml = fetchHtml($baseUrl);
if ($homeHtml === null) {
    fwrite(STDERR, "Cannot fetch homepage: {$baseUrl}\n");
    exit(2);
}

$baseHost = parse_url($baseUrl, PHP_URL_HOST) ?: 'nginx';
$homeLinks = extractLinks($homeHtml, $baseUrl);
$categoryCandidates = [];

foreach ($homeLinks as $link) {
    if (!looksLikeCategoryUrl($link, $baseHost)) {
        continue;
    }

    $catHtml = fetchHtml($link);
    if ($catHtml === null) {
        continue;
    }

    $priceLinks = extractPriceFilterLinks($catHtml, $link);
    if (count($priceLinks) === 0) {
        continue;
    }

    $categoryCandidates[] = [
        'category' => $link,
        'priceLinks' => $priceLinks,
    ];

    if (count($categoryCandidates) >= 3) {
        break;
    }
}

if (count($categoryCandidates) === 0) {
    fwrite(STDERR, "No category URL with price filter found from homepage links.\n");
    exit(3);
}

$epsilon = 0.00001;
$report = [];

foreach ($categoryCandidates as $candidate) {
    $checked = [];
    $usedLinks = array_slice($candidate['priceLinks'], 0, 2);

    foreach ($usedLinks as $filterUrl) {
        $range = parseRange($filterUrl);
        if ($range === null) {
            $checked[] = [
                'filterUrl' => $filterUrl,
                'status' => 'FAIL',
                'reason' => 'Cannot parse price range from URL',
            ];
            continue;
        }

        $filteredHtml = fetchHtml($filterUrl);
        if ($filteredHtml === null) {
            $checked[] = [
                'filterUrl' => $filterUrl,
                'range' => $range,
                'status' => 'FAIL',
                'reason' => 'Cannot fetch filtered page',
            ];
            continue;
        }

        $prices = extractRenderedPrices($filteredHtml);
        if (count($prices) === 0) {
            $checked[] = [
                'filterUrl' => $filterUrl,
                'range' => $range,
                'status' => 'FAIL',
                'reason' => 'No rendered product prices found on filtered page',
            ];
            continue;
        }

        $violations = [];
        foreach ($prices as $price) {
            if ($range['from'] !== null && $price + $epsilon < $range['from']) {
                $violations[] = $price;
                continue;
            }

            if ($range['to'] !== null && $price >= $range['to'] + $epsilon) {
                $violations[] = $price;
                continue;
            }
        }

        $checked[] = [
            'filterUrl' => $filterUrl,
            'range' => $range,
            'status' => count($violations) === 0 ? 'PASS' : 'FAIL',
            'totalPricesChecked' => count($prices),
            'violationCount' => count($violations),
            'violationsSample' => array_slice($violations, 0, 10),
            'minPriceSeen' => min($prices),
            'maxPriceSeen' => max($prices),
        ];
    }

    $report[] = [
        'categoryUrl' => $candidate['category'],
        'checks' => $checked,
    ];
}

echo json_encode([
    'baseUrl' => $baseUrl,
    'categoryCount' => count($report),
    'report' => $report,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
