<?php
declare(strict_types=1);

namespace FashionStore\OrderManagement\Plugin\Block;

use Magento\Sales\Block\Order\History as Subject;

class OrderHistoryPlugin
{
    public function afterGetEmptyOrdersMessage(Subject $subject, $result)
    {
        return __('Bạn chưa có đơn hàng nào.');
    }
}
