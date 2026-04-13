<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Controller\Zalopay;

use FashionStore\CartOptions\Model\Zalopay\OrderService;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;

class Query extends Action implements CsrfAwareActionInterface
{
    private CheckoutSession $checkoutSession;

    private OrderService $orderService;

    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        OrderService $orderService
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->orderService = $orderService;
    }

    public function execute(): Json
    {
        /** @var Json $result */
        $result = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON);
        $incrementId = trim((string) $this->getRequest()->getParam('increment_id', ''));
        $order = $incrementId !== ''
            ? $this->orderService->getOrderByIncrementId($incrementId)
            : $this->checkoutSession->getLastRealOrder();

        if ($order === null || !$order->getEntityId()) {
            return $result->setData(['success' => false, 'message' => __('Unable to resolve the Magento order for ZaloPay query.')]);
        }

        try {
            $payload = $this->orderService->queryForOrder($order);
        } catch (\Throwable $throwable) {
            return $result->setData(['success' => false, 'message' => $throwable->getMessage()]);
        }

        return $result->setData(['success' => true] + $payload);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}