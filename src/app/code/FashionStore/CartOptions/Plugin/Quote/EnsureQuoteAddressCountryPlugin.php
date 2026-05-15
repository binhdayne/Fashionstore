<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Plugin\Quote;

use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;

class EnsureQuoteAddressCountryPlugin
{
    private AddressRepositoryInterface $addressRepository;

    private CustomerRepositoryInterface $customerRepository;

    public function __construct(
        AddressRepositoryInterface $addressRepository,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->addressRepository = $addressRepository;
        $this->customerRepository = $customerRepository;
    }

    public function afterGet(CartRepositoryInterface $subject, \Magento\Quote\Model\Quote $quote): \Magento\Quote\Model\Quote
    {
        $this->ensureAddressCountryAndRegion($quote, $subject);
        return $quote;
    }

    public function afterGetActive(CartRepositoryInterface $subject, \Magento\Quote\Model\Quote $quote): \Magento\Quote\Model\Quote
    {
        $this->ensureAddressCountryAndRegion($quote, $subject);
        return $quote;
    }

    private function ensureAddressCountryAndRegion(\Magento\Quote\Model\Quote $quote, CartRepositoryInterface $repository): void
    {
        try {
            if ($quote->isVirtual()) {
                return;
            }

            $customerId = (int) $quote->getCustomerId();
            if ($customerId <= 0) {
                return;
            }

            $shippingAddress = $quote->getShippingAddress();
            $billingAddress = $quote->getBillingAddress();

            // Check if addresses need country_id
            $needsShippingFix = !$shippingAddress->getCountryId();
            $needsBillingFix = !$billingAddress->getCountryId();

            if (!$needsShippingFix && !$needsBillingFix) {
                return;
            }

            // Resolve customer address
            $customerAddressId = (int) $shippingAddress->getCustomerAddressId();
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

            // Flag to track if we made any changes
            $madeChanges = false;

            // Fix shipping address
            if ($needsShippingFix) {
                if ($customerAddress->getCountryId()) {
                    $shippingAddress->setCountryId($customerAddress->getCountryId());
                    $madeChanges = true;
                }
                if ($customerAddress->getRegionId()) {
                    $shippingAddress->setRegionId($customerAddress->getRegionId());
                    $madeChanges = true;
                }
                if ((int) $shippingAddress->getCustomerAddressId() <= 0) {
                    $shippingAddress->setCustomerAddressId($customerAddressId);
                    $madeChanges = true;
                }
            }

            // Fix billing address  
            if ($needsBillingFix) {
                if ($customerAddress->getCountryId()) {
                    $billingAddress->setCountryId($customerAddress->getCountryId());
                    $madeChanges = true;
                }
                if ($customerAddress->getRegionId()) {
                    $billingAddress->setRegionId($customerAddress->getRegionId());
                    $madeChanges = true;
                }
                if ((int) $billingAddress->getCustomerAddressId() <= 0) {
                    $billingAddress->setCustomerAddressId($customerAddressId);
                    $madeChanges = true;
                }
            }

            // Save quote if we made changes
            if ($madeChanges) {
                $quote->setTotalsCollectedFlag(false);
                $repository->save($quote);
            }
        } catch (NoSuchEntityException | \Throwable $exception) {
            // Silently continue on error
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
