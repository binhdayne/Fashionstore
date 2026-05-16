<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Controller\Zalopay;

use FashionStore\CartOptions\Model\Zalopay\QrImageBuilder;
use FashionStore\CartOptions\Model\Zalopay\OrderService;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;

class Create extends Action implements CsrfAwareActionInterface
{
    private CheckoutSession $checkoutSession;

    private OrderService $orderService;

    private QrImageBuilder $qrImageBuilder;

    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        OrderService $orderService,
        ?QrImageBuilder $qrImageBuilder = null
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->orderService = $orderService;
        $this->qrImageBuilder = $qrImageBuilder ?? ObjectManager::getInstance()->get(QrImageBuilder::class);
    }

    public function execute(): Json
    {
        /** @var Json $result */
        $result = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON);
        $order = $this->checkoutSession->getLastRealOrder();

        if (!$order->getEntityId()) {
            return $result->setData(['success' => false, 'message' => __('No Magento order is available for ZaloPay.')]);
        }

        try {
            $payload = $this->orderService->createForOrder(
                $order,
                (bool) $this->getRequest()->getParam('force')
            );
        } catch (\Throwable $throwable) {
            return $result->setData(['success' => false, 'message' => $throwable->getMessage()]);
        }

        $qrContent = trim((string) ($payload['qr_code'] ?? ''));
        if ($qrContent === '') {
            $qrContent = trim((string) ($payload['order_url'] ?? ''));
        }
        $payload['qr_image'] = $this->qrImageBuilder->buildDataUri($qrContent);

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