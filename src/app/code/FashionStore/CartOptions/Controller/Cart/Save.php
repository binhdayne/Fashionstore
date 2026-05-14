<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Controller\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;

class Save extends Action
{
    private const DEFAULT_COUNTRY_ID = 'VN';

    private CheckoutSession $checkoutSession;

    private FormKeyValidator $formKeyValidator;

    private CartRepositoryInterface $cartRepository;

    private PaymentHelper $paymentHelper;

    private CustomerSession $customerSession;

    private AddressRepositoryInterface $addressRepository;

    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        FormKeyValidator $formKeyValidator,
        CartRepositoryInterface $cartRepository,
        PaymentHelper $paymentHelper,
        CustomerSession $customerSession,
        AddressRepositoryInterface $addressRepository
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->formKeyValidator = $formKeyValidator;
        $this->cartRepository = $cartRepository;
        $this->paymentHelper = $paymentHelper;
        $this->customerSession = $customerSession;
        $this->addressRepository = $addressRepository;
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

        $quote = $this->checkoutSession->getQuote();
        if ($goToCheckout) {
            $this->removeUnselectedItems($quote);
        }
        if (!$quote->getItemsCount()) {
            return $resultRedirect->setPath('checkout/cart');
        }

        try {
            if (!$quote->isVirtual()) {
                $this->updateShippingAddress($quote);
            }

            $paymentMethod = trim((string) $request->getParam('payment_method', ''));
            if ($paymentMethod !== '') {
                $availablePaymentMethods = $this->getAvailablePaymentMethodCodes($quote);
                if (in_array($paymentMethod, $availablePaymentMethods, true)) {
                    $quote->getPayment()->setMethod($paymentMethod);
                }
            }

            $quote->collectTotals();
            $this->cartRepository->save($quote);
            $this->messageManager->addSuccessMessage(__('Da cap nhat thong tin giao hang va thanh toan.'));
        } catch (\Throwable $throwable) {
            $this->messageManager->addErrorMessage(__('Khong the cap nhat thong tin luc nay.'));
        }

        if ($goToCheckout) {
            return $resultRedirect->setPath('checkout');
        }

        return $resultRedirect->setPath('checkout/cart');
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