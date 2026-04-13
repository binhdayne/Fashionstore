<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Controller\Zalopay;

use FashionStore\CartOptions\Model\Zalopay\OrderService;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;

class Callback extends Action implements CsrfAwareActionInterface
{
    private JsonSerializer $jsonSerializer;

    private OrderService $orderService;

    public function __construct(
        Context $context,
        JsonSerializer $jsonSerializer,
        OrderService $orderService
    ) {
        parent::__construct($context);
        $this->jsonSerializer = $jsonSerializer;
        $this->orderService = $orderService;
    }

    public function execute(): Json
    {
        /** @var Json $result */
        $result = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON);
        $requestBody = (string) $this->getRequest()->getContent();

        try {
            $decodedBody = $this->jsonSerializer->unserialize($requestBody);
        } catch (\InvalidArgumentException $exception) {
            return $result->setData(['return_code' => 2, 'return_message' => 'invalid']);
        }

        if (!is_array($decodedBody)) {
            return $result->setData(['return_code' => 2, 'return_message' => 'invalid']);
        }

        return $result->setData($this->orderService->handleCallback($decodedBody));
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