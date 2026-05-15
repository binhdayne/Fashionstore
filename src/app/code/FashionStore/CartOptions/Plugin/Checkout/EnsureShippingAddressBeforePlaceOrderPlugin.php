<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Plugin\Checkout;

use FashionStore\CartOptions\Model\Checkout\DeliveryFeeManager;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote\Address as QuoteAddress;

class EnsureShippingAddressBeforePlaceOrderPlugin
{
    private CartRepositoryInterface $cartRepository;

    private AddressRepositoryInterface $addressRepository;

    private CustomerRepositoryInterface $customerRepository;

    private DeliveryFeeManager $deliveryFeeManager;

    public function __construct(
        CartRepositoryInterface $cartRepository,
        AddressRepositoryInterface $addressRepository,
        CustomerRepositoryInterface $customerRepository,
        DeliveryFeeManager $deliveryFeeManager
    ) {
        $this->cartRepository = $cartRepository;
        $this->addressRepository = $addressRepository;
        $this->customerRepository = $customerRepository;
        $this->deliveryFeeManager = $deliveryFeeManager;
    }

    public function beforePlaceOrder(\Magento\Quote\Model\QuoteManagement $subject, int $cartId, $paymentMethod = null): array
    {
        try {
            $quote = $this->cartRepository->get($cartId);
            if ($quote->isVirtual()) {
                return [$cartId, $paymentMethod];
            }

            $shippingAddress = $quote->getShippingAddress();
            if ($this->isAddressComplete($shippingAddress)) {
                $this->deliveryFeeManager->applyToQuote(
                    $quote,
                    (string) $quote->getData('fs_delivery_method')
                );
                $this->cartRepository->save($quote);
                return [$cartId, $paymentMethod];
            }

            $customerId = (int) $quote->getCustomerId();
            if ($customerId <= 0) {
                return [$cartId, $paymentMethod];
            }

            $customerAddressId = (int) $shippingAddress->getCustomerAddressId();
            if ($customerAddressId <= 0) {
                $customerAddressId = $this->resolvePreferredCustomerAddressId($customerId);
            }

            if ($customerAddressId <= 0) {
                return [$cartId, $paymentMethod];
            }

            $customerAddress = $this->addressRepository->getById($customerAddressId);
            if ((int) $customerAddress->getCustomerId() !== $customerId) {
                return [$cartId, $paymentMethod];
            }

            $shippingAddress->importCustomerAddressData($customerAddress);
            $shippingAddress->setCustomerAddressId($customerAddressId);
            
            // Ensure country_id and region_id are properly set
            if (!$shippingAddress->getCountryId() && $customerAddress->getCountryId()) {
                $shippingAddress->setCountryId($customerAddress->getCountryId());
            }
            if (!$shippingAddress->getRegionId() && $customerAddress->getRegionId()) {
                $shippingAddress->setRegionId($customerAddress->getRegionId());
            }
            
            $shippingAddress->setCollectShippingRates(true);
            $shippingAddress->collectShippingRates();

            if ((string) $shippingAddress->getShippingMethod() === '') {
                $shippingRates = $shippingAddress->getAllShippingRates();
                if (!empty($shippingRates)) {
                    $firstRate = reset($shippingRates);
                    if ($firstRate) {
                        $shippingAddress->setShippingMethod((string) $firstRate->getCode());
                    }
                }
            }

            $billingAddress = $quote->getBillingAddress();
            if (!$this->isAddressComplete($billingAddress)) {
                $billingAddress->importCustomerAddressData($customerAddress);
                $billingAddress->setCustomerAddressId($customerAddressId);
                
                // Ensure country_id and region_id are properly set
                if (!$billingAddress->getCountryId() && $customerAddress->getCountryId()) {
                    $billingAddress->setCountryId($customerAddress->getCountryId());
                }
                if (!$billingAddress->getRegionId() && $customerAddress->getRegionId()) {
                    $billingAddress->setRegionId($customerAddress->getRegionId());
                }
            }

            $this->deliveryFeeManager->applyToQuote(
                $quote,
                (string) $quote->getData('fs_delivery_method')
            );

            $quote->setTotalsCollectedFlag(false);
            $this->cartRepository->save($quote);
        } catch (NoSuchEntityException $exception) {
            return [$cartId, $paymentMethod];
        } catch (\Throwable $exception) {
            return [$cartId, $paymentMethod];
        }

        return [$cartId, $paymentMethod];
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

    private function isAddressComplete(QuoteAddress $address): bool
    {
        $street = $address->getStreet();

        return trim((string) $address->getFirstname()) !== ''
            && trim((string) $address->getLastname()) !== ''
            && trim((string) $address->getCity()) !== ''
            && trim((string) $address->getPostcode()) !== ''
            && trim((string) $address->getTelephone()) !== ''
            && trim((string) $address->getCountryId()) !== ''
            && is_array($street)
            && trim((string) ($street[0] ?? '')) !== '';
    }
}
