<?php
declare(strict_types=1);

namespace FashionStore\OrderManagement\Plugin\Review;

use FashionStore\OrderManagement\Model\ReviewEligibility;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Review\Controller\Product\Post;
use Magento\Review\Model\Review;
use Magento\Review\Model\ReviewFactory;

class ProductPostPlugin
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly CustomerSession $customerSession,
        private readonly ReviewEligibility $reviewEligibility,
        private readonly ManagerInterface $messageManager,
        private readonly RedirectFactory $redirectFactory,
        private readonly ReviewFactory $reviewFactory
    ) {
    }

    public function aroundExecute(Post $subject, callable $proceed)
    {
        $productId = (int) $this->request->getParam('id');
        $orderItemId = (int) $this->request->getParam('order_item_id');
        $customerId = (int) $this->customerSession->getCustomerId();
        $post = $this->request->getPostValue();

        if (is_array($post)) {
            $post['nickname'] = $this->getCustomerName();

            if (trim((string)($post['title'] ?? '')) === '') {
                $post['title'] = (string) __('Nhận xét sản phẩm');
            }

            $this->request->setPostValue($post);
        }

        if ($productId > 0 && $customerId <= 0) {
            $this->messageManager->addErrorMessage(
                __('Bạn cần đăng nhập bằng tài khoản đã mua hàng để nhận xét sản phẩm.')
            );

            return $this->redirectBack();
        }

        if ($productId > 0 && $customerId > 0) {
            if ($orderItemId <= 0) {
                $this->messageManager->addErrorMessage(
                    __('Vui lòng nhận xét sản phẩm từ đơn hàng đã hoàn thành của bạn.')
                );

                return $this->redirectBack();
            }

            if (!$this->reviewEligibility->hasCompletedOrderItemForProduct($customerId, $productId, $orderItemId)) {
                $this->messageManager->addErrorMessage(
                    __('Bạn chỉ có thể nhận xét sản phẩm trong đơn hàng đã hoàn thành.')
                );

                return $this->redirectBack();
            }

            if ($this->reviewEligibility->hasCustomerReviewedOrderItem($customerId, $orderItemId)) {
                $this->messageManager->addErrorMessage(
                    __('Bạn đã nhận xét sản phẩm này trong lần mua này rồi.')
                );

                return $this->redirectBack();
            }
        }

        $latestReviewId = $this->reviewEligibility->getLatestCustomerProductReviewId($customerId, $productId);
        $result = $proceed();
        $newReviewId = $this->reviewEligibility->getLatestCustomerProductReviewId($customerId, $productId);

        if ($newReviewId > $latestReviewId) {
            $this->reviewEligibility->linkReviewToOrderItem($newReviewId, $orderItemId, $customerId, $productId);
            $this->approveReview($newReviewId);
        }

        return $result;
    }

    private function redirectBack()
    {
        $resultRedirect = $this->redirectFactory->create();
        $resultRedirect->setRefererUrl();

        return $resultRedirect;
    }

    private function getCustomerName(): string
    {
        if (!$this->customerSession->isLoggedIn()) {
            return 'Khách hàng';
        }

        $customer = $this->customerSession->getCustomer();
        $name = trim((string) $customer->getName());

        if ($name !== '') {
            return $name;
        }

        $email = trim((string) $customer->getEmail());

        if ($email !== '' && str_contains($email, '@')) {
            return trim((string) strstr($email, '@', true));
        }

        return $email !== '' ? $email : 'Khách hàng';
    }

    private function approveReview(int $reviewId): void
    {
        if ($reviewId <= 0) {
            return;
        }

        $review = $this->reviewFactory->create()->load($reviewId);

        if (!$review->getId()) {
            return;
        }

        $review->setStatusId(Review::STATUS_APPROVED);
        $review->save();
        $review->aggregate();
    }
}
