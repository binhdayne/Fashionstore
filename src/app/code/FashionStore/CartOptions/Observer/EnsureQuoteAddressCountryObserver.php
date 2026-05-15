<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Observer;

use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\NoSuchEntityException;

class EnsureQuoteAddressCountryObserver implements ObserverInterface
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

    public function execute(Observer $observer): void
    {
        try {
            $quote = $observer->getEvent()->getQuote();
            if (!$quote || $quote->isVirtual()) {
                return;
            }

            $customerId = (int) $quote->getCustomerId();
            if ($customerId <= 0) {
                return;
            }

            $shippingAddress = $quote->getShippingAddress();
            if (!$shippingAddress->getCountryId()) {
                $this->ensureAddressCountryAndRegion($shippingAddress, $customerId);
            }

            $billingAddress = $quote->getBillingAddress();
            if (!$billingAddress->getCountryId()) {
                $this->ensureAddressCountryAndRegion($billingAddress, $customerId);
            }
        } catch (\Throwable $exception) {
            // Silently continue on error
        }
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

            if ($customerAddress->getCountryId()) {
                $address->setCountryId($customerAddress->getCountryId());
            }

            if ($customerAddress->getRegionId()) {
                $address->setRegionId($customerAddress->getRegionId());
            }

            if ((int) $address->getCustomerAddressId() <= 0) {
                $address->setCustomerAddressId($customerAddressId);
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
