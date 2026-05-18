<?php
declare(strict_types=1);

namespace FashionStore\OrderManagement\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Review\Model\Review;
use Magento\Sales\Model\Order;

class ReviewEligibility
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    public function hasCustomerReviewedProduct(int $customerId, int $productId): bool
    {
        if ($customerId <= 0 || $productId <= 0) {
            return false;
        }

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(['r' => $this->resourceConnection->getTableName('review')], ['review_id'])
            ->joinInner(
                ['rd' => $this->resourceConnection->getTableName('review_detail')],
                'r.review_id = rd.review_id',
                []
            )
            ->where('r.entity_pk_value = ?', $productId)
            ->where('r.entity_id = ?', $this->getProductReviewEntityId())
            ->where('rd.customer_id = ?', $customerId)
            ->limit(1);

        return (bool) $connection->fetchOne($select);
    }

    public function hasCustomerReviewedOrderItem(int $customerId, int $orderItemId): bool
    {
        if ($customerId <= 0 || $orderItemId <= 0) {
            return false;
        }

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->getReviewLinkTable(), ['review_id'])
            ->where('customer_id = ?', $customerId)
            ->where('order_item_id = ?', $orderItemId)
            ->limit(1);

        return (bool) $connection->fetchOne($select);
    }

    public function hasCompletedOrderForProduct(int $customerId, int $productId): bool
    {
        if ($customerId <= 0 || $productId <= 0) {
            return false;
        }

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(['o' => $this->resourceConnection->getTableName('sales_order')], ['entity_id'])
            ->joinInner(
                ['i' => $this->resourceConnection->getTableName('sales_order_item')],
                'o.entity_id = i.order_id',
                []
            )
            ->where('o.customer_id = ?', $customerId)
            ->where('o.state = ?', Order::STATE_COMPLETE)
            ->where('i.product_id = ?', $productId)
            ->limit(1);

        return (bool) $connection->fetchOne($select);
    }

    public function hasCompletedOrderItemForProduct(int $customerId, int $productId, int $orderItemId): bool
    {
        if ($customerId <= 0 || $productId <= 0 || $orderItemId <= 0) {
            return false;
        }

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(['o' => $this->resourceConnection->getTableName('sales_order')], ['entity_id'])
            ->joinInner(
                ['i' => $this->resourceConnection->getTableName('sales_order_item')],
                'o.entity_id = i.order_id',
                []
            )
            ->where('o.customer_id = ?', $customerId)
            ->where('o.state = ?', Order::STATE_COMPLETE)
            ->where('i.item_id = ?', $orderItemId)
            ->where('i.product_id = ?', $productId)
            ->limit(1);

        return (bool) $connection->fetchOne($select);
    }

    public function canCustomerReviewOrderItem(int $customerId, int $productId, int $orderItemId): bool
    {
        return $this->hasCompletedOrderItemForProduct($customerId, $productId, $orderItemId)
            && !$this->hasCustomerReviewedOrderItem($customerId, $orderItemId);
    }

    public function getLatestCustomerProductReviewId(int $customerId, int $productId): int
    {
        if ($customerId <= 0 || $productId <= 0) {
            return 0;
        }

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(['r' => $this->resourceConnection->getTableName('review')], ['review_id'])
            ->joinInner(
                ['rd' => $this->resourceConnection->getTableName('review_detail')],
                'r.review_id = rd.review_id',
                []
            )
            ->where('r.entity_pk_value = ?', $productId)
            ->where('r.entity_id = ?', $this->getProductReviewEntityId())
            ->where('rd.customer_id = ?', $customerId)
            ->order('r.review_id DESC')
            ->limit(1);

        return (int) $connection->fetchOne($select);
    }

    public function linkReviewToOrderItem(int $reviewId, int $orderItemId, int $customerId, int $productId): void
    {
        if ($reviewId <= 0 || $orderItemId <= 0 || $customerId <= 0 || $productId <= 0) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $connection->insertOnDuplicate(
            $this->getReviewLinkTable(),
            [
                'review_id' => $reviewId,
                'order_item_id' => $orderItemId,
                'customer_id' => $customerId,
                'product_id' => $productId,
            ],
            ['review_id', 'customer_id', 'product_id']
        );
    }

    public function canCustomerReviewProduct(int $customerId, int $productId): bool
    {
        return $this->hasCompletedOrderForProduct($customerId, $productId)
            && !$this->hasCustomerReviewedProduct($customerId, $productId);
    }

    private function getProductReviewEntityId(): int
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName('review_entity'), ['entity_id'])
            ->where('entity_code = ?', Review::ENTITY_PRODUCT_CODE)
            ->limit(1);

        return (int) $connection->fetchOne($select);
    }

    private function getReviewLinkTable(): string
    {
        $tableName = $this->resourceConnection->getTableName('fashionstore_order_item_review');
        $connection = $this->resourceConnection->getConnection();

        if (!$connection->isTableExists($tableName)) {
            $connection->query(
                "CREATE TABLE IF NOT EXISTS `{$tableName}` (
                    `link_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `review_id` BIGINT UNSIGNED NOT NULL,
                    `order_item_id` INT UNSIGNED NOT NULL,
                    `customer_id` INT UNSIGNED NOT NULL,
                    `product_id` INT UNSIGNED NOT NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`link_id`),
                    UNIQUE KEY `FASHIONSTORE_ORDER_ITEM_REVIEW_ORDER_ITEM` (`order_item_id`),
                    UNIQUE KEY `FASHIONSTORE_ORDER_ITEM_REVIEW_REVIEW` (`review_id`),
                    KEY `FASHIONSTORE_ORDER_ITEM_REVIEW_CUSTOMER_PRODUCT` (`customer_id`, `product_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        }

        return $tableName;
    }
}
