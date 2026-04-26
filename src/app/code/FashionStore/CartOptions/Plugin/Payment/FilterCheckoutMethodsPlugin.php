<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Plugin\Payment;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Payment\Model\MethodInterface;
use FashionStore\CartOptions\Model\Payment\BankTransferQr;
use FashionStore\CartOptions\Model\Payment\Cod;
use FashionStore\CartOptions\Model\Payment\Momo;
use FashionStore\CartOptions\Model\Payment\Zalopay;
use FashionStore\CartOptions\Model\Payment\Vnpay;

class FilterCheckoutMethodsPlugin
{
    private const ALLOWED_METHODS = [
        'vnpay',
        'fashionstore_vnpay',
        'fashionstore_momo',
        'fashionstore_cod',
        'fashionstore_zalopay',
        'fashionstore_banktransfer_qr',
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

            if ($code === 'fashionstore_momo') {
                $method->setData('title', 'MoMo');
            } elseif ($code === 'vnpay' || $code === 'fashionstore_vnpay') {
                $method->setData('title', 'Thanh toán bằng VNPAY');
            }
        }

        $filteredMethods = array_values(array_filter(
            $result,
            static fn (MethodInterface $method): bool => in_array((string) $method->getCode(), self::ALLOWED_METHODS, true)
        ));

        return $this->appendMissingMethods($filteredMethods);
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

        // Ensure VNPAY (from FashionStore CartOptions module) is available
        if (!in_array('vnpay', $existingCodes, true)) {
            /** @var Vnpay $vnpay */
            $vnpay = $this->objectManager->create(Vnpay::class);
            $vnpay->setData('title', 'Thanh toán bằng VNPAY');
            $methods[] = $vnpay;
        }

        if (!in_array('fashionstore_cod', $existingCodes, true)) {
            /** @var Cod $cod */
            $cod = $this->objectManager->create(Cod::class);
            $cod->setData('title', 'Thanh toan khi nhan hang');
            $methods[] = $cod;
        }

        if (!in_array('fashionstore_momo', $existingCodes, true)) {
            /** @var Momo $momo */
            $momo = $this->objectManager->create(Momo::class);
            $momo->setData('title', 'MoMo');
            $methods[] = $momo;
        }

        if (!in_array('fashionstore_zalopay', $existingCodes, true)) {
            /** @var Zalopay $zalopay */
            $zalopay = $this->objectManager->create(Zalopay::class);
            $zalopay->setData('title', 'ZaloPay');
            $methods[] = $zalopay;
        }

        if (!in_array('fashionstore_banktransfer_qr', $existingCodes, true)) {
            /** @var BankTransferQr $bankTransferQr */
            $bankTransferQr = $this->objectManager->create(BankTransferQr::class);
            $bankTransferQr->setData('title', 'Chuyen khoan QR');
            $methods[] = $bankTransferQr;
        }

        return $methods;
    }
}