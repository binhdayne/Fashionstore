<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Plugin\Checkout;

use FashionStore\CartOptions\Model\Checkout\DeliveryFeeManager;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\PaymentInterface;
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
        $this->ensureShippingAddress($cartId);

        return [$cartId, $paymentMethod];
    }

    public function beforeSet(
        \Magento\Quote\Model\PaymentMethodManagement $subject,
        int $cartId,
        PaymentInterface $method
    ): array {
        $this->ensureShippingAddress($cartId);

        return [$cartId, $method];
    }

    private function ensureShippingAddress(int $cartId): void
    {
        try {
            $quote = $this->cartRepository->get($cartId);
            if ($quote->isVirtual()) {
                return;
            }

            $shippingAddress = $quote->getShippingAddress();
            if ($this->isAddressComplete($shippingAddress)) {
                $this->deliveryFeeManager->applyToQuote(
                    $quote,
                    (string) $quote->getData('fs_delivery_method')
                );
                $this->cartRepository->save($quote);
                return;
            }

            $billingAddress = $quote->getBillingAddress();
            if ($this->isAddressComplete($billingAddress)) {
                $this->copyQuoteAddress($billingAddress, $shippingAddress);
                $shippingAddress->setSameAsBilling(1);
                $this->prepareShippingMethod($shippingAddress);

                $this->deliveryFeeManager->applyToQuote(
                    $quote,
                    (string) $quote->getData('fs_delivery_method')
                );

                $quote->setTotalsCollectedFlag(false);
                $this->cartRepository->save($quote);
                return;
            }

            $customerId = (int) $quote->getCustomerId();
            if ($customerId <= 0) {
                return;
            }

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

            $shippingAddress->importCustomerAddressData($customerAddress);
            $shippingAddress->setCustomerAddressId($customerAddressId);
            $this->prepareShippingMethod($shippingAddress);

            $billingAddress = $quote->getBillingAddress();
            if (!$this->isAddressComplete($billingAddress)) {
                $billingAddress->importCustomerAddressData($customerAddress);
                $billingAddress->setCustomerAddressId($customerAddressId);
            }

            $this->deliveryFeeManager->applyToQuote(
                $quote,
                (string) $quote->getData('fs_delivery_method')
            );

            $quote->setTotalsCollectedFlag(false);
            $this->cartRepository->save($quote);
        } catch (NoSuchEntityException $exception) {
            return;
        } catch (\Throwable $exception) {
            return;
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

    private function copyQuoteAddress(QuoteAddress $source, QuoteAddress $target): void
    {
        $target->setFirstname((string) $source->getFirstname());
        $target->setLastname((string) $source->getLastname());
        $target->setCompany((string) $source->getCompany());
        $target->setStreet($source->getStreet());
        $target->setCity((string) $source->getCity());
        $target->setRegion((string) $source->getRegion());
        $target->setRegionId($source->getRegionId());
        $target->setCountryId((string) $source->getCountryId());
        $target->setPostcode((string) $source->getPostcode());
        $target->setTelephone((string) $source->getTelephone());
        $target->setFax((string) $source->getFax());
        $target->setEmail((string) $source->getEmail());
        $target->setCustomerAddressId($source->getCustomerAddressId());
    }

    private function prepareShippingMethod(QuoteAddress $shippingAddress): void
    {
        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->collectShippingRates();

        if ((string) $shippingAddress->getShippingMethod() !== '') {
            return;
        }

        $shippingRates = $shippingAddress->getAllShippingRates();
        if (empty($shippingRates)) {
            return;
        }

        $firstRate = reset($shippingRates);
        if ($firstRate) {
            $shippingAddress->setShippingMethod((string) $firstRate->getCode());
        }
    }
}
