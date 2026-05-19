<?php
declare(strict_types=1);

namespace FashionStore\OrderManagement\Model;

use Magento\Sales\Model\Order;

class ShippingTimeline
{
    /**
     * @return array<string, mixed>
     */
    public function getTrackingData(Order $order): array
    {
        $state = (string) $order->getState();
        if (!in_array($state, [Order::STATE_PROCESSING, Order::STATE_COMPLETE], true)) {
            return [];
        }

        $shipping = $order->getShippingAddress();
        if (!$shipping) {
            return [];
        }

        $route = $this->resolveRoute($shipping);
        $carrier = $this->resolveCarrier((int) $order->getEntityId());
        $createdAt = new \DateTimeImmutable((string) $order->getCreatedAt());
        $elapsedDays = max(0, (int) $createdAt->diff(new \DateTimeImmutable('now'))->format('%a'));
        $currentIndex = $state === Order::STATE_COMPLETE ? 6 : min(5, max(1, $elapsedDays + 1));

        $steps = [
            [
                'label' => 'Đơn hàng đã được xác nhận',
                'location' => 'Hệ thống FashionStore',
                'time' => $createdAt->modify('+20 minutes'),
            ],
            [
                'label' => 'Shop đã đóng gói đơn hàng',
                'location' => 'Kho FashionStore Hà Nội',
                'time' => $createdAt->modify('+4 hours'),
            ],
            [
                'label' => 'Đã bàn giao cho đơn vị vận chuyển',
                'location' => $route['origin_hub'],
                'time' => $createdAt->modify('+1 day +1 hour'),
            ],
            [
                'label' => 'Đã đến kho trung chuyển',
                'location' => $route['regional_hub'],
                'time' => $createdAt->modify('+2 days +3 hours'),
            ],
            [
                'label' => 'Đang chuyển đến bưu cục giao hàng',
                'location' => $route['local_hub'],
                'time' => $createdAt->modify('+3 days +2 hours'),
            ],
            [
                'label' => 'Shipper đang giao hàng',
                'location' => $route['last_mile'],
                'time' => $createdAt->modify('+4 days +8 hours'),
            ],
            [
                'label' => 'Giao hàng thành công',
                'location' => $route['destination'],
                'time' => $createdAt->modify('+5 days +6 hours'),
            ],
        ];

        foreach ($steps as $index => &$step) {
            $step['is_done'] = $index <= $currentIndex;
            $step['is_current'] = $index === $currentIndex;
            $step['time_label'] = $this->formatTime($step['time']);
            unset($step['time']);
        }
        unset($step);

        return [
            'carrier' => $carrier['name'],
            'carrier_code' => $carrier['code'],
            'tracking_number' => $this->buildTrackingNumber($carrier['code'], $order),
            'eta' => $this->formatDate($createdAt->modify('+5 days')),
            'current_status' => $steps[$currentIndex]['label'],
            'current_location' => $steps[$currentIndex]['location'],
            'steps' => $steps,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function resolveCarrier(int $orderId): array
    {
        $carriers = [
            ['code' => 'GHN', 'name' => 'Giao Hàng Nhanh'],
            ['code' => 'GHTK', 'name' => 'Giao Hàng Tiết Kiệm'],
            ['code' => 'JNT', 'name' => 'J&T Express'],
            ['code' => 'VTP', 'name' => 'Viettel Post'],
        ];

        return $carriers[$orderId % count($carriers)];
    }

    /**
     * @return array<string, string>
     */
    private function resolveRoute(\Magento\Sales\Api\Data\OrderAddressInterface $shipping): array
    {
        $city = $this->normalize((string) $shipping->getCity());
        $region = $this->normalize((string) $shipping->getRegion());
        $street = $this->normalize(implode(' ', $shipping->getStreet() ?: []));
        $address = trim($street . ' ' . $city . ' ' . $region);

        if (str_contains($address, 'ha noi') || str_contains($address, 'hanoi')) {
            return [
                'origin_hub' => 'Kho FashionStore Hà Nội',
                'regional_hub' => 'Kho phân loại Hà Nội',
                'local_hub' => 'Bưu cục Cầu Giấy',
                'last_mile' => 'Tuyến giao nội thành Hà Nội',
                'destination' => 'Địa chỉ nhận hàng tại Hà Nội',
            ];
        }

        if (str_contains($address, 'da nang') || str_contains($address, 'danang')) {
            return [
                'origin_hub' => 'Kho FashionStore Hà Nội',
                'regional_hub' => 'Kho trung chuyển miền Trung',
                'local_hub' => 'Bưu cục Hải Châu',
                'last_mile' => 'Tuyến giao nội thành Đà Nẵng',
                'destination' => 'Địa chỉ nhận hàng tại Đà Nẵng',
            ];
        }

        if (str_contains($address, 'ho chi minh') || str_contains($address, 'hcm') || str_contains($address, 'sai gon')) {
            return [
                'origin_hub' => 'Kho FashionStore Hà Nội',
                'regional_hub' => 'Kho trung chuyển TP.HCM',
                'local_hub' => 'Bưu cục giao hàng gần bạn',
                'last_mile' => 'Tuyến giao nội thành TP.HCM',
                'destination' => 'Địa chỉ nhận hàng tại TP.HCM',
            ];
        }

        return [
            'origin_hub' => 'Kho FashionStore Hà Nội',
            'regional_hub' => 'Kho trung chuyển miền Bắc',
            'local_hub' => 'Bưu cục giao hàng khu vực',
            'last_mile' => 'Shipper đang di chuyển đến địa chỉ nhận',
            'destination' => 'Địa chỉ nhận hàng của bạn',
        ];
    }

    private function normalize(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        return strtolower($value);
    }

    private function buildTrackingNumber(string $carrierCode, Order $order): string
    {
        $seed = implode('|', [
            (string) $order->getEntityId(),
            (string) $order->getIncrementId(),
            (string) $order->getCreatedAt(),
            (string) $order->getCustomerEmail(),
        ]);
        $hash = strtoupper(substr(hash('crc32b', $seed), 0, 8));

        return $carrierCode . '-' . substr($hash, 0, 4) . '-' . substr($hash, 4, 4);
    }

    private function formatTime(\DateTimeImmutable $date): string
    {
        return $date->format('d/m/Y H:i');
    }

    private function formatDate(\DateTimeImmutable $date): string
    {
        return $date->format('d/m/Y');
    }
}
