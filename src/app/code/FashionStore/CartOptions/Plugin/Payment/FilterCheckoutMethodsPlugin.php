<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Plugin\Payment;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Payment\Model\MethodInterface;
use FashionStore\CartOptions\Model\Payment\BankTransferQr;
use FashionStore\CartOptions\Model\Payment\Cod;
use FashionStore\CartOptions\Model\Payment\Zalopay;
use FashionStore\CartOptions\Model\Payment\Vnpay;

class FilterCheckoutMethodsPlugin
{
    private const ALLOWED_METHODS = [
        'fashionstore_cod',
        'fashionstore_banktransfer_qr',
        'fashionstore_zalopay',
        'vnpay',
        'fashionstore_vnpay',
    ];

    private RequestInterface $request;

    private ObjectManagerInterface $objectManager;

    public function __construct(RequestInterface $request, ObjectManagerInterface $objectManager)
    {
        $this->request = $request;
        $this->objectManager = $objectManager;
    }

    /**
     * @param MethodInterface[] $result
     * @return MethodInterface[]
     */
    public function afterGetAvailableMethods(\Magento\Payment\Model\MethodList $subject, array $result): array
    {
        foreach ($result as $method) {
            $code = (string) $method->getCode();

            if ($code === 'fashionstore_cod') {
                $method->setData('title', 'Thanh toán offline khi nhận hàng');
            } elseif ($code === 'fashionstore_banktransfer_qr') {
                $method->setData('title', 'Chuyển khoản QR');
            } elseif ($code === 'vnpay' || $code === 'fashionstore_vnpay') {
                $method->setData('title', 'Thanh toán bằng VNPAY');
            }
        }

        $filteredMethods = array_values(array_filter(
            $result,
            static fn (MethodInterface $method): bool => in_array((string) $method->getCode(), self::ALLOWED_METHODS, true)
        ));

        return $this->sortMethods($this->appendMissingMethods($filteredMethods));
    }

    /**
     * @param MethodInterface[] $methods
     * @return MethodInterface[]
     */
    private function appendMissingMethods(array $methods): array
    {
        $existingCodes = array_map(
            static fn (MethodInterface $method): string => (string) $method->getCode(),
            $methods
        );

        if (!in_array('fashionstore_cod', $existingCodes, true)) {
            /** @var Cod $cod */
            $cod = $this->objectManager->create(Cod::class);
            $cod->setData('title', 'Thanh toán offline khi nhận hàng');
            $methods[] = $cod;
        }

        if (!in_array('fashionstore_banktransfer_qr', $existingCodes, true)) {
            /** @var BankTransferQr $bankTransferQr */
            $bankTransferQr = $this->objectManager->create(BankTransferQr::class);
            $bankTransferQr->setData('title', 'Chuyển khoản QR');
            $methods[] = $bankTransferQr;
        }

        if (!in_array('fashionstore_zalopay', $existingCodes, true)) {
            /** @var Zalopay $zalopay */
            $zalopay = $this->objectManager->create(Zalopay::class);
            $zalopay->setData('title', 'ZaloPay');
            $methods[] = $zalopay;
        }

        if (!in_array('vnpay', $existingCodes, true)) {
            /** @var Vnpay $vnpay */
            $vnpay = $this->objectManager->create(Vnpay::class);
            $vnpay->setData('title', 'Thanh toán bằng VNPAY');
            $methods[] = $vnpay;
        }

        return $methods;
    }

    /**
     * @param MethodInterface[] $methods
     * @return MethodInterface[]
     */
    private function sortMethods(array $methods): array
    {
        $priority = array_flip(self::ALLOWED_METHODS);

        usort($methods, static function (MethodInterface $left, MethodInterface $right) use ($priority): int {
            $leftCode = (string) $left->getCode();
            $rightCode = (string) $right->getCode();

            return ($priority[$leftCode] ?? 999) <=> ($priority[$rightCode] ?? 999);
        });

        return $methods;
    }
}
