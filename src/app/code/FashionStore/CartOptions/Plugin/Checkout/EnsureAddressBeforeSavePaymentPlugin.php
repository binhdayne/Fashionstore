<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Plugin\Checkout;

use Magento\Checkout\Model\PaymentInformationManagement;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;

class EnsureAddressBeforeSavePaymentPlugin
{
    private CartRepositoryInterface $cartRepository;

    private AddressRepositoryInterface $addressRepository;

    private CustomerRepositoryInterface $customerRepository;

    public function __construct(
        CartRepositoryInterface $cartRepository,
        AddressRepositoryInterface $addressRepository,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->cartRepository = $cartRepository;
        $this->addressRepository = $addressRepository;
        $this->customerRepository = $customerRepository;
    }

    public function beforeSavePaymentInformation(
        PaymentInformationManagement $subject,
        $cartId,
        $paymentMethod,
        $billingAddress = null
    ): array {
        try {
            $quote = $this->cartRepository->get((int) $cartId);
            if ($quote->isVirtual()) {
                return [$cartId, $paymentMethod, $billingAddress];
            }

            $shippingAddress = $quote->getShippingAddress();
            $customerId = (int) $quote->getCustomerId();

            // Log for debugging
            \error_log("EnsureAddressBeforeSavePaymentPlugin: Processing cart {$cartId}, customer {$customerId}");
            \error_log("EnsureAddressBeforeSavePaymentPlugin: Shipping address country_id: " . ($shippingAddress->getCountryId() ?? 'NULL'));

            // Ensure shipping address has country_id and region_id
            if ($customerId > 0 && !$shippingAddress->getCountryId()) {
                \error_log("EnsureAddressBeforeSavePaymentPlugin: Shipping address missing country_id, attempting to load from customer address");
                $this->ensureAddressCountryAndRegion($shippingAddress, $customerId);
                \error_log("EnsureAddressBeforeSavePaymentPlugin: After ensureAddress, shipping country_id: " . ($shippingAddress->getCountryId() ?? 'NULL'));
            }

            // Ensure billing address has country_id and region_id
            $quoteBillingAddress = $quote->getBillingAddress();
            if ($customerId > 0 && !$quoteBillingAddress->getCountryId()) {
                \error_log("EnsureAddressBeforeSavePaymentPlugin: Billing address missing country_id, attempting to load from customer address");
                $this->ensureAddressCountryAndRegion($quoteBillingAddress, $customerId);
            }

            $this->cartRepository->save($quote);
            \error_log("EnsureAddressBeforeSavePaymentPlugin: Quote saved successfully");
        } catch (NoSuchEntityException | \Throwable $exception) {
            // Continue with original flow on error
            \error_log("EnsureAddressBeforeSavePaymentPlugin: Error - " . $exception->getMessage());
        }

        return [$cartId, $paymentMethod, $billingAddress];
    }

    private function ensureAddressCountryAndRegion(\Magento\Quote\Model\Quote\Address $address, int $customerId): void
    {
        try {
            $customerAddressId = (int) $address->getCustomerAddressId();
            if ($customerAddressId <= 0) {
                $customerAddressId = $this->resolvePreferredCustomerAddressId($customerId);
            }

            if ($customerAddressId <= 0) {
                return;
            }

            $customerAddress = $this->addressRepository->getById($customerAddressId);
            if ((int) $customerAddress->getCustomerId() !== $customerId) {
                return;
            }

            if (!$address->getCountryId() && $customerAddress->getCountryId()) {
                $address->setCountryId($customerAddress->getCountryId());
            }

            if (!$address->getRegionId() && $customerAddress->getRegionId()) {
                $address->setRegionId($customerAddress->getRegionId());
            }
        } catch (NoSuchEntityException | \Throwable $exception) {
            // Continue on error
        }
    }

    private function resolvePreferredCustomerAddressId(int $customerId): int
    {
        try {
            $customer = $this->customerRepository->getById($customerId);
        } catch (NoSuchEntityException $exception) {
            return 0;
        }

        $defaultShippingId = (int) $customer->getDefaultShipping();
        if ($defaultShippingId > 0) {
            return $defaultShippingId;
        }

        $defaultBillingId = (int) $customer->getDefaultBilling();
        if ($defaultBillingId > 0) {
            return $defaultBillingId;
        }

        foreach ((array) $customer->getAddresses() as $address) {
            $addressId = (int) $address->getId();
            if ($addressId > 0) {
                return $addressId;
            }
        }

        return 0;
    }
}
