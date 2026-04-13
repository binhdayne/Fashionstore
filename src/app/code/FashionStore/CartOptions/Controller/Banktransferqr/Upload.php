<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Controller\Banktransferqr;

use FashionStore\CartOptions\Model\BankTransferQr\OrderService;
use FashionStore\CartOptions\Model\BankTransferQr\ProofUploader;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Exception\LocalizedException;

class Upload extends Action implements CsrfAwareActionInterface
{
    private OrderService $orderService;

    private ProofUploader $proofUploader;

    public function __construct(
        Context $context,
        OrderService $orderService,
        ProofUploader $proofUploader
    ) {
        parent::__construct($context);
        $this->orderService = $orderService;
        $this->proofUploader = $proofUploader;
    }

    public function execute(): Json
    {
        /** @var Json $result */
        $result = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON);
        $incrementId = trim((string) $this->getRequest()->getParam('increment_id'));
        $proofNote = trim((string) $this->getRequest()->getParam('proof_note'));

        if ($incrementId === '') {
            return $result->setData(['success' => false, 'message' => __('Order increment id is required.')]);
        }

        if (!isset($_FILES['proof_image'])) {
            return $result->setData(['success' => false, 'message' => __('Please choose an image file before uploading.')]);
        }

        $order = $this->orderService->getOrderByIncrementId($incrementId);
        if ($order === null || !$order->getEntityId()) {
            return $result->setData(['success' => false, 'message' => __('The order for this transfer proof could not be found.')]);
        }

        try {
            $relativePath = $this->proofUploader->save('proof_image');
            $payload = $this->orderService->saveProofForOrder($order, $relativePath, $proofNote);
        } catch (LocalizedException $exception) {
            return $result->setData(['success' => false, 'message' => $exception->getMessage()]);
        } catch (\Throwable $throwable) {
            return $result->setData(['success' => false, 'message' => __('Unable to upload payment proof right now.')]);
        }

        return $result->setData([
            'success' => true,
            'message' => __('Da tai len minh chung thanh toan thanh cong. Shop se doi soat trong thoi gian som nhat.'),
            'proof_url' => $this->proofUploader->getFileUrl($relativePath),
        ] + $payload);
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