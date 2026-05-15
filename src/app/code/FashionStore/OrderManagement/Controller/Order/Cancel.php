<?php
declare(strict_types=1);

namespace FashionStore\OrderManagement\Controller\Order;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class Cancel extends Action implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly CustomerSession $customerSession
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        if (!$this->formKeyValidator->validate($this->getRequest())) {
            return $result->setData([
                'success' => false,
                'message' => __('Yêu cầu không hợp lệ.'),
            ]);
        }

        if (!$this->customerSession->isLoggedIn()) {
            return $result->setData([
                'success' => false,
                'message' => __('Vui lòng đăng nhập để thực hiện thao tác này.'),
            ]);
        }

        $orderId = (int) $this->getRequest()->getParam('order_id');
        $reason = trim((string) $this->getRequest()->getParam('reason', ''));

        if ($orderId <= 0) {
            return $result->setData([
                'success' => false,
                'message' => __('Đơn hàng không hợp lệ.'),
            ]);
        }

        try {
            $order = $this->orderRepository->get($orderId);

            if ((int) $order->getCustomerId() !== (int) $this->customerSession->getCustomerId()) {
                return $result->setData([
                    'success' => false,
                    'message' => __('Bạn không có quyền hủy đơn hàng này.'),
                ]);
            }

            if (!$order->canCancel()) {
                return $result->setData([
                    'success' => false,
                    'message' => __('Đơn hàng này không thể hủy lúc này.'),
                ]);
            }

            if ($order->getState() !== Order::STATE_NEW && $order->getStatus() !== 'pending') {
                return $result->setData([
                    'success' => false,
                    'message' => __('Chỉ có thể hủy đơn hàng đang ở trạng thái chờ xử lý.'),
                ]);
            }

            $order->cancel();
            $cancelReason = $reason !== '' ? $reason : (string) __('Không có lý do');
            $order->addCommentToStatusHistory(
                __('Khách hàng hủy đơn. Lý do: %1', $cancelReason),
                Order::STATE_CANCELED,
                false
            );

            $this->orderRepository->save($order);

            return $result->setData([
                'success' => true,
                'message' => __('Đơn hàng #%1 đã được hủy thành công.', $order->getIncrementId()),
            ]);
        } catch (\Magento\Framework\Exception\NoSuchEntityException) {
            return $result->setData([
                'success' => false,
                'message' => __('Không tìm thấy đơn hàng.'),
            ]);
        } catch (\Throwable) {
            return $result->setData([
                'success' => false,
                'message' => __('Có lỗi xảy ra. Vui lòng thử lại.'),
            ]);
        }
    }
}
