<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Controller\Cart;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Store\Model\StoreManagerInterface;

class Buynow extends Action
{
    private const SESSION_KEY_ACTIVE = 'fashionstore_buy_now_active';
    private const SESSION_KEY_ITEM_IDS = 'fashionstore_buy_now_item_ids';
    private const SESSION_KEY_ORIGINAL_QUOTE_ID = 'fashionstore_buy_now_original_quote_id';
    private const SESSION_KEY_QUOTE_ID = 'fashionstore_buy_now_quote_id';

    private FormKeyValidator $formKeyValidator;

    private CheckoutSession $checkoutSession;

    private ProductRepositoryInterface $productRepository;

    private QuoteFactory $quoteFactory;

    private CartRepositoryInterface $cartRepository;

    private StoreManagerInterface $storeManager;

    private CustomerSession $customerSession;

    private CustomerRepositoryInterface $customerRepository;

    private UrlInterface $urlBuilder;

    public function __construct(
        Context $context,
        FormKeyValidator $formKeyValidator,
        CheckoutSession $checkoutSession,
        ProductRepositoryInterface $productRepository,
        QuoteFactory $quoteFactory,
        CartRepositoryInterface $cartRepository,
        StoreManagerInterface $storeManager,
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository
    ) {
        parent::__construct($context);
        $this->formKeyValidator = $formKeyValidator;
        $this->checkoutSession = $checkoutSession;
        $this->productRepository = $productRepository;
        $this->quoteFactory = $quoteFactory;
        $this->cartRepository = $cartRepository;
        $this->storeManager = $storeManager;
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->urlBuilder = $context->getUrl();
    }

    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $request = $this->getRequest();

        if (!$request->isPost() || !$this->formKeyValidator->validate($request)) {
            $this->messageManager->addErrorMessage(__('Your session has expired.'));

            return $resultRedirect->setPath('checkout/cart');
        }

        try {
            if (!$this->customerSession->isLoggedIn()) {
                $targetUrl = $this->resolveLoginTargetUrl();
                $this->customerSession->setBeforeAuthUrl($targetUrl);
                $this->customerSession->setAfterAuthUrl($targetUrl);
                $this->messageManager->addErrorMessage(__('Vui lòng đăng nhập để sử dụng Mua ngay.'));
                return $resultRedirect->setPath('customer/account/login');
            }

            $this->restoreOriginalQuoteIfNeeded();

            $productId = (int) $request->getParam('product');
            if ($productId <= 0) {
                throw new LocalizedException(__('We cannot find the product you want to buy.'));
            }

            $store = $this->storeManager->getStore();
            $product = $this->productRepository->getById($productId, false, (int) $store->getId());
            $originalQuoteId = (int) $this->checkoutSession->getQuoteId();
            $sourceQuote = null;

            if ($originalQuoteId > 0) {
                try {
                    $sourceQuote = $this->cartRepository->get($originalQuoteId);
                } catch (NoSuchEntityException $exception) {
                    $sourceQuote = null;
                }
            }

            $buyNowQuote = $this->quoteFactory->create();

            $buyNowQuote->setStoreId((int) $store->getId());
            $buyNowQuote->setStore($store);
            $buyNowQuote->setIsActive(true);
            $buyNowQuote->setInventoryProcessed(false);
            $this->applyCurrencyContextFromStore($buyNowQuote);

            if ($this->customerSession->isLoggedIn()) {
                $customer = $this->customerRepository->getById((int) $this->customerSession->getCustomerId());
                $buyNowQuote->setCustomer($customer);
                $buyNowQuote->setCustomerIsGuest(0);
            } else {
                $buyNowQuote->setCustomerIsGuest(1);
            }

            $buyNowRequest = $request->getParams();
            unset($buyNowRequest['return_url'], $buyNowRequest['uenc'], $buyNowRequest['form_key'], $buyNowRequest['fashionstore_buy_now']);

            $result = $buyNowQuote->addProduct($product, new DataObject($buyNowRequest));
            if (is_string($result)) {
                throw new LocalizedException(__($result));
            }

            if ($sourceQuote && (int) $sourceQuote->getId() > 0) {
                $this->copyCheckoutContextFromSourceQuote($buyNowQuote, $sourceQuote);
            }

            $buyNowQuote->collectTotals();
            $this->cartRepository->save($buyNowQuote);

            $buyNowItemIds = [];
            foreach ($buyNowQuote->getAllItems() as $item) {
                if ($item->getId()) {
                    $buyNowItemIds[] = (int) $item->getId();
                }
            }

            $this->checkoutSession->replaceQuote($buyNowQuote);
            $this->checkoutSession->setData(self::SESSION_KEY_ACTIVE, true);
            $this->checkoutSession->setData(self::SESSION_KEY_QUOTE_ID, (int) $buyNowQuote->getId());
            $this->checkoutSession->setData(self::SESSION_KEY_ITEM_IDS, $buyNowItemIds);

            if ($originalQuoteId > 0 && $originalQuoteId !== (int) $buyNowQuote->getId()) {
                $this->checkoutSession->setData(self::SESSION_KEY_ORIGINAL_QUOTE_ID, $originalQuoteId);
            } else {
                $this->checkoutSession->unsetData(self::SESSION_KEY_ORIGINAL_QUOTE_ID);
            }

            return $resultRedirect->setUrl($this->urlBuilder->getUrl('checkout') . '#payment');
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage(__('We can\'t start checkout for this product right now.'));
        }

        return $resultRedirect->setPath('checkout/cart');
    }

    private function restoreOriginalQuoteIfNeeded(): void
    {
        $buyNowQuoteId = (int) $this->checkoutSession->getData(self::SESSION_KEY_QUOTE_ID);
        $originalQuoteId = (int) $this->checkoutSession->getData(self::SESSION_KEY_ORIGINAL_QUOTE_ID);

        if ($buyNowQuoteId <= 0 || $originalQuoteId <= 0 || (int) $this->checkoutSession->getQuoteId() !== $buyNowQuoteId) {
            return;
        }

        try {
            $originalQuote = $this->cartRepository->get($originalQuoteId);
            $this->checkoutSession->replaceQuote($originalQuote);
        } catch (NoSuchEntityException $exception) {
        }
    }

    private function resolveLoginTargetUrl(): string
    {
        $baseUrl = $this->urlBuilder->getBaseUrl();
        $refererUrl = (string) $this->getRequest()->getServer('HTTP_REFERER');

        if ($refererUrl !== '' && str_starts_with($refererUrl, $baseUrl)) {
            return $refererUrl;
        }

        $productId = (int) $this->getRequest()->getParam('product');
        if ($productId > 0) {
            try {
                $product = $this->productRepository->getById($productId, false, (int) $this->storeManager->getStore()->getId());

                return (string) $product->getProductUrl();
            } catch (NoSuchEntityException $exception) {
            }
        }

        return $baseUrl;
    }

    private function applyCurrencyContextFromStore(Quote $quote): void
    {
        $store = $quote->getStore();

        $quote->setGlobalCurrencyCode((string) $store->getCurrentCurrencyCode());
        $quote->setBaseCurrencyCode((string) $store->getBaseCurrencyCode());
        $quote->setStoreCurrencyCode((string) $store->getCurrentCurrencyCode());
        $quote->setQuoteCurrencyCode((string) $store->getCurrentCurrencyCode());

        $baseToQuoteRate = (float) ($store->getBaseCurrency()->getRate($store->getCurrentCurrency()) ?: 1);
        $storeToBaseRate = $baseToQuoteRate > 0 ? 1 / $baseToQuoteRate : 1;

        $quote->setBaseToGlobalRate(1.0);
        $quote->setStoreToBaseRate($storeToBaseRate);
        $quote->setStoreToQuoteRate(1.0);
        $quote->setBaseToQuoteRate($baseToQuoteRate > 0 ? $baseToQuoteRate : 1.0);
    }

    private function copyCheckoutContextFromSourceQuote(Quote $targetQuote, Quote $sourceQuote): void
    {
        if ($sourceQuote->isVirtual()) {
            return;
        }

        $this->copyAddressData($targetQuote->getShippingAddress(), $sourceQuote->getShippingAddress());
        $this->copyAddressData($targetQuote->getBillingAddress(), $sourceQuote->getBillingAddress());

        $shippingMethod = (string) $sourceQuote->getShippingAddress()->getShippingMethod();
        if ($shippingMethod !== '') {
            $targetQuote->getShippingAddress()->setShippingMethod($shippingMethod);
        }

        $paymentMethod = (string) $sourceQuote->getPayment()->getMethod();
        if ($paymentMethod !== '') {
            $targetQuote->getPayment()->setMethod($paymentMethod);
        }
    }

    private function copyAddressData(AddressInterface $targetAddress, AddressInterface $sourceAddress): void
    {
        if (!$sourceAddress->getId()) {
            return;
        }

        $targetAddress->addData($sourceAddress->getData());
        $targetAddress->setId(null);
        $targetAddress->setQuoteId(null);
        $targetAddress->setCustomerAddressId($sourceAddress->getCustomerAddressId());
    }
}