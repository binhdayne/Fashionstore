<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Plugin\Checkout;

use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;

class EnsureAddressCountryPlugin
{
    private const DEFAULT_COUNTRY_ID = 'VN';
    private const FALLBACK_SHIPPING_METHOD = 'fashionstore_ghtk_standard';
    private const FALLBACK_SHIPPING_DESC = 'Giao Hang Tiet Kiem - GHTK - Giao tiet kiem';

    private CustomerSession $customerSession;
    private AddressRepositoryInterface $addressRepository;
    private CartRepositoryInterface $cartRepository;

    public function __construct(
        CustomerSession $customerSession,
        AddressRepositoryInterface $addressRepository,
        CartRepositoryInterface $cartRepository
    ) {
        $this->customerSession = $customerSession;
        $this->addressRepository = $addressRepository;
        $this->cartRepository = $cartRepository;
    }

    public function beforeSavePaymentInformation(
        PaymentInformationManagementInterface $subject,
        int $cartId,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null
    ): array {
        try {
            $this->fixQuoteByCartId($cartId);
        } catch (\Throwable $e) {}
        return [$cartId, $paymentMethod, $billingAddress];
    }

    public function beforeSavePaymentInformationAndPlaceOrder(
        PaymentInformationManagementInterface $subject,
        int $cartId,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null
    ): array {
        try {
            $this->fixQuoteByCartId($cartId);
        } catch (\Throwable $e) {}
        return [$cartId, $paymentMethod, $billingAddress];
    }

    private function fixQuoteByCartId(int $cartId): void
    {
        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->cartRepository->getActive($cartId);

        if (!$quote || $quote->isVirtual()) {
            return;
        }

        $shippingAddress = $quote->getShippingAddress();
        $billingAddress  = $quote->getBillingAddress();
        $changed = false;

        // Fix country_id nếu thiếu
        if (!$shippingAddress->getCountryId()) {
            $countryId = $this->resolveCountryId($shippingAddress);
            $shippingAddress->setCountryId($countryId);
            $billingAddress->setCountryId($countryId);
            $changed = true;
        }

        if (!$billingAddress->getCountryId()) {
            $billingAddress->setCountryId(
                $shippingAddress->getCountryId() ?: self::DEFAULT_COUNTRY_ID
            );
            $changed = true;
        }

        // Fix shipping method nếu thiếu
        if (!$shippingAddress->getShippingMethod()) {
            $this->setShippingMethod($shippingAddress);
            $changed = true;
        }

        if ($changed) {
            $shippingAddress->setCollectShippingRates(true);
            $quote->collectTotals();
            $this->cartRepository->save($quote);
        }
    }

    private function resolveCountryId(\Magento\Quote\Model\Quote\Address $address): string
    {
        $customerAddressId = (int) $address->getCustomerAddressId();
        if ($customerAddressId > 0 && $this->customerSession->isLoggedIn()) {
            try {
                $customerAddress = $this->addressRepository->getById($customerAddressId);
                $countryId = (string) $customerAddress->getCountryId();
                if ($countryId !== '') {
                    return $countryId;
                }
            } catch (\Throwable $e) {}
        }
        return self::DEFAULT_COUNTRY_ID;
    }

    private function setShippingMethod(\Magento\Quote\Model\Quote\Address $shippingAddress): void
    {
        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->collectShippingRates();
        $rates = $shippingAddress->getAllShippingRates();

        foreach ($rates as $rate) {
            if (!$rate->getErrorMessage()) {
                $shippingAddress->setShippingMethod(
                    $rate->getCarrier() . '_' . $rate->getMethod()
                );
                $shippingAddress->setShippingDescription(
                    $rate->getCarrierTitle() . ' - ' . $rate->getMethodTitle()
                );
                return;
            }
        }

        // Fallback flatrate
        $shippingAddress->setShippingMethod(self::FALLBACK_SHIPPING_METHOD);
        $shippingAddress->setShippingDescription(self::FALLBACK_SHIPPING_DESC);
    }
}
