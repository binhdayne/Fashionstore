<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Plugin\Checkout;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Controller\Cart\Index as CartIndex;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\DataObject;
use Magento\Quote\Api\CartRepositoryInterface;

class RestoreRemovedCartItemsPlugin
{
    private CheckoutSession $checkoutSession;

    private CartRepositoryInterface $cartRepository;

    private ProductRepositoryInterface $productRepository;

    public function __construct(
        CheckoutSession $checkoutSession,
        CartRepositoryInterface $cartRepository,
        ProductRepositoryInterface $productRepository
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->cartRepository = $cartRepository;
        $this->productRepository = $productRepository;
    }

    public function beforeExecute(CartIndex $subject): void
    {
        $removedItems = $this->checkoutSession->getData('fashionstore_removed_cart_items');

        if (empty($removedItems) || !is_array($removedItems)) {
            return;
        }

        $quote = $this->checkoutSession->getQuote();
        $restored = false;

        foreach ($removedItems as $itemData) {
            try {
                $productId = (int) ($itemData['product_id'] ?? 0);
                $qty = (float) ($itemData['qty'] ?? 1);
                $buyRequest = $itemData['buy_request'] ?? [];

                if ($productId <= 0) {
                    continue;
                }

                $product = $this->productRepository->getById($productId);

                // Kiểm tra item chưa có trong cart (tránh duplicate)
                $alreadyInCart = false;
                foreach ($quote->getAllItems() as $existingItem) {
                    if ((int) $existingItem->getProductId() === $productId) {
                        $alreadyInCart = true;
                        break;
                    }
                }

                if ($alreadyInCart) {
                    continue;
                }

                $requestObject = new DataObject($buyRequest);
                $requestObject->setData('qty', $qty);
                $quote->addProduct($product, $requestObject);
                $restored = true;

            } catch (\Throwable $e) {
                // Bỏ qua item lỗi, tiếp tục khôi phục các item còn lại
                continue;
            }
        }

        if ($restored) {
            $quote->collectTotals();
            $this->cartRepository->save($quote);
        }

        // Xóa session sau khi khôi phục
        $this->checkoutSession->unsetData('fashionstore_removed_cart_items');
    }
}
