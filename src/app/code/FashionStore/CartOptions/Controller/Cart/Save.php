<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Controller\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\DataObject;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Framework\Serialize\Serializer\Json;

class Save extends Action
{
    private const DEFAULT_COUNTRY_ID = 'VN';
    private const SESSION_KEY_ACTIVE = 'fashionstore_buy_now_active';
    private const SESSION_KEY_ITEM_IDS = 'fashionstore_buy_now_item_ids';
    private const SESSION_KEY_ORIGINAL_QUOTE_ID = 'fashionstore_buy_now_original_quote_id';
    private const SESSION_KEY_QUOTE_ID = 'fashionstore_buy_now_quote_id';

    private CheckoutSession $checkoutSession;

    private FormKeyValidator $formKeyValidator;

    private CartRepositoryInterface $cartRepository;

    private PaymentHelper $paymentHelper;

    private CustomerSession $customerSession;

    private AddressRepositoryInterface $addressRepository;

    private QuoteFactory $quoteFactory;

    private CustomerRepositoryInterface $customerRepository;

    private Json $jsonSerializer;

    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        FormKeyValidator $formKeyValidator,
        CartRepositoryInterface $cartRepository,
        PaymentHelper $paymentHelper,
        CustomerSession $customerSession,
        AddressRepositoryInterface $addressRepository,
        QuoteFactory $quoteFactory,
        CustomerRepositoryInterface $customerRepository,
        Json $jsonSerializer
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->formKeyValidator = $formKeyValidator;
        $this->cartRepository = $cartRepository;
        $this->paymentHelper = $paymentHelper;
        $this->customerSession = $customerSession;
        $this->addressRepository = $addressRepository;
        $this->quoteFactory = $quoteFactory;
        $this->customerRepository = $customerRepository;
        $this->jsonSerializer = $jsonSerializer;
    }

    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $request = $this->getRequest();
        $goToCheckout = (bool) $request->getParam('go_to_checkout', false);

        if (!$request->isPost() || !$this->formKeyValidator->validate($request)) {
            $this->messageManager->addErrorMessage(__('Yeu cau khong hop le.'));
            return $resultRedirect->setPath('checkout/cart');
        }

        $this->restoreOriginalQuoteIfNeeded();

        $quote = $this->checkoutSession->getQuote();
        if ($goToCheckout) {
            $this->removeUnselectedItems($quote);
        }
        if (!$quote->getItemsCount()) {
            return $resultRedirect->setPath('checkout/cart');
        }

        try {
            $selectedItems = $this->getSelectedCheckoutItems();

            if ($goToCheckout && $selectedItems !== []) {
                $checkoutQuote = $this->buildCheckoutQuoteFromSelection($quote, $selectedItems);

                if (!$checkoutQuote->isVirtual()) {
                    if ($this->hasShippingAddressInput()) {
                        $this->updateShippingAddress($checkoutQuote);
                    } else {
                        $this->retainExistingShippingMethod($checkoutQuote);
                    }
                }

                $this->applyPaymentMethod($checkoutQuote);
                $checkoutQuote->collectTotals();
                $this->cartRepository->save($checkoutQuote);
                $this->activateTemporaryCheckoutQuote($checkoutQuote, (int) $quote->getId());

                return $resultRedirect->setPath('checkout');
            }

            if (!$quote->isVirtual()) {
                $this->updateShippingAddress($quote);
            }

            $this->applyPaymentMethod($quote);

            $quote->collectTotals();
            $this->cartRepository->save($quote);
            $this->messageManager->addSuccessMessage(__('Da cap nhat thong tin giao hang va thanh toan.'));
        } catch (\Throwable $throwable) {
            $this->messageManager->addErrorMessage(__('Khong the cap nhat thong tin luc nay.'));

            return $resultRedirect->setPath('checkout/cart');
        }

        if ($goToCheckout) {
            return $resultRedirect->setPath('checkout');
        }

        return $resultRedirect->setPath('checkout/cart');
    }

    /**
     * @return array<int, float>
     */
    private function getSelectedCheckoutItems(): array
    {
        $selection = trim((string) $this->getRequest()->getParam('checkout_selection', ''));

        if ($selection === '') {
            return [];
        }

        try {
            $decodedSelection = $this->jsonSerializer->unserialize($selection);
        } catch (\InvalidArgumentException $exception) {
            return [];
        }

        if (!is_array($decodedSelection)) {
            return [];
        }

        $selectedItems = [];

        foreach ($decodedSelection as $selectedItem) {
            if (!is_array($selectedItem)) {
                continue;
            }

            $itemId = (int) ($selectedItem['item_id'] ?? 0);
            $qty = (float) ($selectedItem['qty'] ?? 0);

            if ($itemId <= 0 || $qty <= 0) {
                continue;
            }

            $selectedItems[$itemId] = $qty;
        }

        return $selectedItems;
    }

    /**
     * @param array<int, float> $selectedItems
     */
    private function buildCheckoutQuoteFromSelection(Quote $sourceQuote, array $selectedItems): Quote
    {
        $checkoutQuote = $this->quoteFactory->create();
        $checkoutQuote->setStoreId((int) $sourceQuote->getStoreId());
        $checkoutQuote->setStore($sourceQuote->getStore());
        $checkoutQuote->setIsActive(true);
        $checkoutQuote->setInventoryProcessed(false);
        $this->applyCurrencyContextFromSourceQuote($checkoutQuote, $sourceQuote);

        if ($this->customerSession->isLoggedIn()) {
            $customer = $this->customerRepository->getById((int) $this->customerSession->getCustomerId());
            $checkoutQuote->setCustomer($customer);
            $checkoutQuote->setCustomerIsGuest(0);
        } else {
            $checkoutQuote->setCustomerIsGuest(1);
            $checkoutQuote->setCustomerEmail((string) $sourceQuote->getCustomerEmail());
        }

        foreach ($selectedItems as $itemId => $qty) {
            $quoteItem = $sourceQuote->getItemById($itemId);
            if (!$quoteItem || $quoteItem->getParentItemId()) {
                continue;
            }

            $buyRequest = $quoteItem->getBuyRequest();
            $requestData = $buyRequest ? $buyRequest->getData() : [];
            $requestData['qty'] = $qty;

            $result = $checkoutQuote->addProduct($quoteItem->getProduct(), new DataObject($requestData));
            if (is_string($result)) {
                throw new LocalizedException(__($result));
            }
        }

        if (count($checkoutQuote->getAllVisibleItems()) === 0) {
            throw new LocalizedException(__('Vui long chon it nhat mot san pham de thanh toan.'));
        }

        $this->copyAddressFromSourceQuote($checkoutQuote, $sourceQuote);

        return $checkoutQuote;
    }

    private function copyAddressFromSourceQuote(Quote $targetQuote, Quote $sourceQuote): void
    {
        $targetShippingAddress = $targetQuote->getShippingAddress();
        $sourceShippingAddress = $sourceQuote->getShippingAddress();

        if ($sourceShippingAddress && $sourceShippingAddress->getId()) {
            $targetShippingAddress->addData($sourceShippingAddress->getData());
            $targetShippingAddress->setId(null);
            $targetShippingAddress->setQuoteId(null);
            $targetShippingAddress->setCustomerAddressId($sourceShippingAddress->getCustomerAddressId());

            if ($sourceShippingAddress->getShippingMethod()) {
                $targetShippingAddress->setShippingMethod((string) $sourceShippingAddress->getShippingMethod());
            }
        }

        $targetBillingAddress = $targetQuote->getBillingAddress();
        $sourceBillingAddress = $sourceQuote->getBillingAddress();

        if ($sourceBillingAddress && $sourceBillingAddress->getId()) {
            $targetBillingAddress->addData($sourceBillingAddress->getData());
            $targetBillingAddress->setId(null);
            $targetBillingAddress->setQuoteId(null);
            $targetBillingAddress->setCustomerAddressId($sourceBillingAddress->getCustomerAddressId());
        }
    }

    private function applyCurrencyContextFromSourceQuote(Quote $targetQuote, Quote $sourceQuote): void
    {
        $targetQuote->setGlobalCurrencyCode((string) $sourceQuote->getGlobalCurrencyCode());
        $targetQuote->setBaseCurrencyCode((string) $sourceQuote->getBaseCurrencyCode());
        $targetQuote->setStoreCurrencyCode((string) $sourceQuote->getStoreCurrencyCode());
        $targetQuote->setQuoteCurrencyCode((string) $sourceQuote->getQuoteCurrencyCode());

        $targetQuote->setStoreToBaseRate($this->normalizeRate((float) $sourceQuote->getStoreToBaseRate()));
        $targetQuote->setStoreToQuoteRate($this->normalizeRate((float) $sourceQuote->getStoreToQuoteRate()));
        $targetQuote->setBaseToGlobalRate($this->normalizeRate((float) $sourceQuote->getBaseToGlobalRate()));
        $targetQuote->setBaseToQuoteRate($this->normalizeRate((float) $sourceQuote->getBaseToQuoteRate()));
    }

    private function normalizeRate(float $rate): float
    {
        return $rate > 0 ? $rate : 1.0;
    }

    private function applyPaymentMethod(Quote $quote): void
    {
        $paymentMethod = trim((string) $this->getRequest()->getParam('payment_method', ''));
        if ($paymentMethod === '') {
            return;
        }

        $availablePaymentMethods = $this->getAvailablePaymentMethodCodes($quote);
        if (in_array($paymentMethod, $availablePaymentMethods, true)) {
            $quote->getPayment()->setMethod($paymentMethod);
        }
    }

    private function activateTemporaryCheckoutQuote(Quote $checkoutQuote, int $originalQuoteId): void
    {
        $temporaryItemIds = [];

        foreach ($checkoutQuote->getAllVisibleItems() as $item) {
            if ($item->getId()) {
                $temporaryItemIds[] = (int) $item->getId();
            }
        }

        $this->checkoutSession->replaceQuote($checkoutQuote);
        $this->checkoutSession->setData(self::SESSION_KEY_ACTIVE, true);
        $this->checkoutSession->setData(self::SESSION_KEY_QUOTE_ID, (int) $checkoutQuote->getId());
        $this->checkoutSession->setData(self::SESSION_KEY_ITEM_IDS, $temporaryItemIds);

        if ($originalQuoteId > 0 && $originalQuoteId !== (int) $checkoutQuote->getId()) {
            $this->checkoutSession->setData(self::SESSION_KEY_ORIGINAL_QUOTE_ID, $originalQuoteId);
        } else {
            $this->checkoutSession->unsetData(self::SESSION_KEY_ORIGINAL_QUOTE_ID);
        }
    }

    private function restoreOriginalQuoteIfNeeded(): void
    {
        $temporaryQuoteId = (int) $this->checkoutSession->getData(self::SESSION_KEY_QUOTE_ID);
        $originalQuoteId = (int) $this->checkoutSession->getData(self::SESSION_KEY_ORIGINAL_QUOTE_ID);

        if ($temporaryQuoteId <= 0 || $originalQuoteId <= 0 || (int) $this->checkoutSession->getQuoteId() !== $temporaryQuoteId) {
            return;
        }

        try {
            $originalQuote = $this->cartRepository->get($originalQuoteId);
            $this->checkoutSession->replaceQuote($originalQuote);
        } catch (LocalizedException $exception) {
        }
    }

    private function updateShippingAddress(Quote $quote): void
    {
        $shippingAddress = $quote->getShippingAddress();
        $billingAddress = $quote->getBillingAddress();
        $currentShippingMethod = (string) $shippingAddress->getShippingMethod();
        $selectedCustomerAddressId = (int) $this->getRequest()->getParam('selected_customer_address_id', 0);

        if ($selectedCustomerAddressId > 0) {
            if (!$this->customerSession->isLoggedIn()) {
                throw new LocalizedException(__('Dia chi da luu chi danh cho tai khoan dang nhap.'));
            }

            $customerAddress = $this->addressRepository->getById($selectedCustomerAddressId);
            if ((int) $customerAddress->getCustomerId() !== (int) $this->customerSession->getCustomerId()) {
                throw new LocalizedException(__('Dia chi giao hang khong hop le.'));
            }

            $shippingAddress->importCustomerAddressData($customerAddress);
            $billingAddress->importCustomerAddressData($customerAddress);
        } else {
            $addressData = [
                'firstname' => $this->getTrimmedRequestValue('shipping_firstname'),
                'lastname' => $this->getTrimmedRequestValue('shipping_lastname'),
                'telephone' => $this->getTrimmedRequestValue('shipping_telephone'),
                'street' => [
                    $this->getTrimmedRequestValue('shipping_street_1'),
                    $this->getTrimmedRequestValue('shipping_street_2'),
                ],
                'city' => $this->getTrimmedRequestValue('shipping_city'),
                'region' => $this->getTrimmedRequestValue('shipping_region'),
                'postcode' => $this->getTrimmedRequestValue('shipping_postcode'),
                'country_id' => $this->getTrimmedRequestValue('shipping_country_id') ?: self::DEFAULT_COUNTRY_ID,
                'save_in_address_book' => 0,
            ];

            $shippingAddress->addData($addressData);
            $billingAddress->addData($addressData);
            $shippingAddress->setCustomerAddressId(null);
            $billingAddress->setCustomerAddressId(null);
        }

        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->setSameAsBilling(1);
        $shippingAddress->setSaveInAddressBook(0);
        $billingAddress->setSaveInAddressBook(0);

        if ($currentShippingMethod !== '') {
            $shippingAddress->setShippingMethod($currentShippingMethod);
        }
    }

    private function retainExistingShippingMethod(Quote $quote): void
    {
        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setCollectShippingRates(true);

        if ((string) $shippingAddress->getShippingMethod() === '') {
            return;
        }

        $shippingAddress->setShippingMethod((string) $shippingAddress->getShippingMethod());
    }

    private function hasShippingAddressInput(): bool
    {
        if ((int) $this->getRequest()->getParam('selected_customer_address_id', 0) > 0) {
            return true;
        }

        $addressKeys = [
            'shipping_firstname',
            'shipping_lastname',
            'shipping_telephone',
            'shipping_street_1',
            'shipping_street_2',
            'shipping_city',
            'shipping_region',
            'shipping_postcode',
            'shipping_country_id',
        ];

        foreach ($addressKeys as $key) {
            if ($this->getTrimmedRequestValue($key) !== '') {
                return true;
            }
        }

        return false;
    }

    private function getAvailablePaymentMethodCodes(Quote $quote): array
    {
        $codes = [];

        foreach ($this->paymentHelper->getStoreMethods($quote->getStoreId(), $quote) as $method) {
            $codes[] = (string) $method->getCode();
        }

        return $codes;
    }

    private function getTrimmedRequestValue(string $key): string
    {
        return trim((string) $this->getRequest()->getParam($key, ''));
    }

    private function removeUnselectedItems(Quote $quote): void
    {
        $selectedItemsJson = trim((string) $this->getRequest()->getParam('checkout_selection', ''));

        if ($selectedItemsJson === '' || $selectedItemsJson === '[]') {
            return;
        }

        try {
            $selectedItems = json_decode($selectedItemsJson, true);
        } catch (\Throwable $e) {
            return;
        }

        if (!is_array($selectedItems) || empty($selectedItems)) {
            return;
        }

        $selectedItemIds = [];
        foreach ($selectedItems as $item) {
            if (!empty($item['item_id'])) {
                $selectedItemIds[] = (int) $item['item_id'];
            }
        }

        if (empty($selectedItemIds)) {
            return;
        }

        // Lưu danh sách item bị xóa vào session để khôi phục sau
        $removedItems = [];
        foreach ($quote->getAllItems() as $item) {
            if (!in_array((int) $item->getId(), $selectedItemIds, true)) {
                $removedItems[] = [
                    'item_id'     => (int) $item->getId(),
                    'product_id'  => (int) $item->getProductId(),
                    'sku'         => (string) $item->getSku(),
                    'qty'         => (float) $item->getQty(),
                    'buy_request' => $item->getBuyRequest()
                        ? $item->getBuyRequest()->toArray()
                        : [],
                ];
                $quote->removeItem($item->getId());
            }
        }

        // Lưu vào session để RestoreRemovedCartItemsPlugin có thể khôi phục
        if (!empty($removedItems)) {
            $this->checkoutSession->setData(
                'fashionstore_removed_cart_items',
                $removedItems
            );
        }
    }
}