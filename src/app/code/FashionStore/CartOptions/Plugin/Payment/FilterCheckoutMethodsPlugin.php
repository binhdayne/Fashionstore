<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Plugin\Payment;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Payment\Model\MethodInterface;
use FashionStore\CartOptions\Model\Payment\BankTransferQr;
use FashionStore\CartOptions\Model\Payment\Cod;

class FilterCheckoutMethodsPlugin
{
    private const ALLOWED_METHODS = [
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
        if ($this->request->getFullActionName() !== 'checkout_index_index') {
            return $result;
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

        if (!in_array('fashionstore_cod', $existingCodes, true)) {
            /** @var Cod $cod */
            $cod = $this->objectManager->create(Cod::class);
            $cod->setData('title', 'Thanh toan khi nhan hang');
            $methods[] = $cod;
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