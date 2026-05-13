<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Controller\Cart;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\RawFactory;

class Test extends Action
{
    private RawFactory $rawFactory;

    public function __construct(Context $context, RawFactory $rawFactory)
    {
        parent::__construct($context);
        $this->rawFactory = $rawFactory;
    }

    public function execute()
    {
        $result = $this->rawFactory->create();
        return $result->setContents('ok-test-controller');
    }
}
