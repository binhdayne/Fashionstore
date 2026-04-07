<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Block\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Quote\Model\Quote;

class Options extends Template
{
    private const DEFAULT_COUNTRY_ID = 'VN';

    private CheckoutSession $checkoutSession;

    private FormKey $formKey;

    private PaymentHelper $paymentHelper;

    private CustomerSession $customerSession;

    private CustomerRepositoryInterface $customerRepository;

    private Json $jsonSerializer;

    public function __construct(
        Template\Context $context,
        CheckoutSession $checkoutSession,
        FormKey $formKey,
        PaymentHelper $paymentHelper,
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        Json $jsonSerializer,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->checkoutSession = $checkoutSession;
        $this->formKey = $formKey;
        $this->paymentHelper = $paymentHelper;
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->jsonSerializer = $jsonSerializer;
    }

    public function canRender(): bool
    {
        $quote = $this->getQuote();

        return (bool) $quote->getItemsCount() && !$quote->isVirtual();
    }

    public function getQuote(): Quote
    {
        return $this->checkoutSession->getQuote();
    }

    public function getFormAction(): string
    {
        return $this->getUrl('fashionstore_cartoptions/cart/save');
    }

    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }

    public function getDeliveryData(): array
    {
        $address = $this->getQuote()->getShippingAddress();

        if (!$this->hasManualQuoteAddress()) {
            $selectedAddress = $this->getSelectedSavedAddress();
            if ($selectedAddress !== null) {
                return $selectedAddress['data'];
            }
        }

        $street = $address->getStreet();

        return [
            'firstname' => (string) $address->getFirstname(),
            'lastname' => (string) $address->getLastname(),
            'telephone' => (string) $address->getTelephone(),
            'street_1' => (string) ($street[0] ?? ''),
            'street_2' => (string) ($street[1] ?? ''),
            'city' => (string) $address->getCity(),
            'region' => $this->normalizeRegionValue($address->getRegion()),
            'postcode' => (string) $address->getPostcode(),
            'country_id' => (string) ($address->getCountryId() ?: self::DEFAULT_COUNTRY_ID),
        ];
    }

    public function getAvailablePaymentMethods(): array
    {
        $paymentMethods = [];

        foreach ($this->paymentHelper->getStoreMethods($this->getQuote()->getStoreId(), $this->getQuote()) as $method) {
            $code = (string) $method->getCode();
            if (!$this->isPreferredPaymentMethod($code)) {
                continue;
            }

            $paymentMethods[] = $this->buildPaymentPresentation(
                $code,
                (string) ($method->getTitle() ?: $method->getConfigData('title') ?: $code)
            );
        }

        return $paymentMethods;
    }

    public function getSelectedPaymentMethod(): string
    {
        $selectedMethod = (string) $this->getQuote()->getPayment()->getMethod();
        $availableMethods = $this->getAvailablePaymentMethods();

        foreach ($availableMethods as $method) {
            if ($method['code'] === $selectedMethod) {
                return $selectedMethod;
            }
        }

        return $availableMethods[0]['code'] ?? '';
    }

    public function getVisiblePaymentLabels(): string
    {
        return 'COD, VNPay, MoMo';
    }

    public function isCustomerLoggedIn(): bool
    {
        return $this->customerSession->isLoggedIn();
    }

    public function getSavedAddresses(): array
    {
        if (!$this->isCustomerLoggedIn()) {
            return [];
        }

        $customer = $this->customerRepository->getById((int) $this->customerSession->getCustomerId());
        $selectedAddressId = $this->getSelectedCustomerAddressId();
        $addresses = [];

        foreach ($customer->getAddresses() as $address) {
            $street = $address->getStreet();
            $addressData = [
                'firstname' => (string) $address->getFirstname(),
                'lastname' => (string) $address->getLastname(),
                'telephone' => (string) $address->getTelephone(),
                'street_1' => (string) ($street[0] ?? ''),
                'street_2' => (string) ($street[1] ?? ''),
                'city' => (string) $address->getCity(),
                'region' => $this->normalizeRegionValue($address->getRegion()),
                'postcode' => (string) $address->getPostcode(),
                'country_id' => (string) ($address->getCountryId() ?: self::DEFAULT_COUNTRY_ID),
            ];

            $addresses[] = [
                'id' => (int) $address->getId(),
                'name' => trim((string) $address->getFirstname() . ' ' . (string) $address->getLastname()),
                'telephone' => (string) $address->getTelephone(),
                'inline' => implode(', ', array_filter([
                    $addressData['street_1'],
                    $addressData['street_2'],
                    $addressData['city'],
                    $addressData['region'],
                    $addressData['postcode'],
                ])),
                'is_default' => (bool) $address->isDefaultShipping(),
                'is_selected' => (int) $address->getId() === $selectedAddressId,
                'data' => $addressData,
                'data_json' => $this->jsonSerializer->serialize($addressData),
            ];
        }

        return $addresses;
    }

    public function getSelectedCustomerAddressId(): int
    {
        $quoteAddressId = (int) $this->getQuote()->getShippingAddress()->getCustomerAddressId();
        if ($quoteAddressId > 0) {
            return $quoteAddressId;
        }

        if ($this->hasManualQuoteAddress() || !$this->isCustomerLoggedIn()) {
            return 0;
        }

        $customer = $this->customerRepository->getById((int) $this->customerSession->getCustomerId());
        foreach ($customer->getAddresses() as $address) {
            if ($address->isDefaultShipping()) {
                return (int) $address->getId();
            }
        }

        return 0;
    }

    public function shouldShowManualAddressForm(): bool
    {
        return !$this->isCustomerLoggedIn() || $this->getSelectedCustomerAddressId() === 0;
    }

    private function isPreferredPaymentMethod(string $code): bool
    {
        $normalizedCode = strtolower($code);

        return $normalizedCode === 'cashondelivery'
            || str_contains($normalizedCode, 'vnpay')
            || str_contains($normalizedCode, 'momo');
    }

    private function hasManualQuoteAddress(): bool
    {
        $address = $this->getQuote()->getShippingAddress();

        return trim((string) $address->getFirstname()) !== ''
            || trim((string) $address->getLastname()) !== ''
            || trim((string) $address->getTelephone()) !== ''
            || trim((string) $address->getCity()) !== ''
            || trim((string) $address->getPostcode()) !== ''
            || !empty($address->getStreet());
    }

    private function getSelectedSavedAddress(): ?array
    {
        foreach ($this->getSavedAddresses() as $address) {
            if ($address['is_selected']) {
                return $address;
            }
        }

        return null;
    }

    private function buildPaymentPresentation(string $code, string $fallbackTitle): array
    {
        $normalizedCode = strtolower($code);
        $iconPath = 'FashionStore_CartOptions::images/payment-cod.svg';
        $title = $fallbackTitle;
        $description = 'Hoan tat don hang tren trang checkout an toan va nhanh gon.';
        $badge = 'Pay';
        $modifier = 'generic';

        if ($normalizedCode === 'cashondelivery') {
            $title = 'Thanh toan khi nhan hang';
            $description = 'Thanh toan bang tien mat khi shipper giao don den dia chi cua ban.';
            $badge = 'COD';
            $modifier = 'cod';
        } elseif (str_contains($normalizedCode, 'vnpay')) {
            $title = 'VNPay';
            $description = 'Quet QR, ATM noi dia, the quoc te va ung dung ngan hang tuong thich VNPay.';
            $badge = 'Gateway';
            $modifier = 'vnpay';
            $iconPath = 'FashionStore_CartOptions::images/payment-vnpay.svg';
        } elseif (str_contains($normalizedCode, 'momo')) {
            $title = 'MoMo';
            $description = 'Vi dien tu MoMo cho thao tac nhanh tren mobile, ATM va the thanh toan.';
            $badge = 'Wallet';
            $modifier = 'momo';
            $iconPath = 'FashionStore_CartOptions::images/payment-momo.svg';
        }

        return [
            'code' => $code,
            'title' => $title,
            'configured_title' => $fallbackTitle,
            'description' => $description,
            'badge' => $badge,
            'modifier' => $modifier,
            'icon_url' => $this->getViewFileUrl($iconPath),
        ];
    }

    private function normalizeRegionValue(mixed $region): string
    {
        if (is_string($region) || is_numeric($region)) {
            return trim((string) $region);
        }

        if (is_object($region)) {
            if (method_exists($region, 'getRegion') && is_scalar($region->getRegion())) {
                return trim((string) $region->getRegion());
            }

            if (method_exists($region, 'getRegionCode') && is_scalar($region->getRegionCode())) {
                return trim((string) $region->getRegionCode());
            }

            if (method_exists($region, '__toString')) {
                return trim((string) $region);
            }
        }

        return '';
    }
}