<?php

namespace FashionStore\Recommendation\Console\Command;

use Magento\Framework\App\ResourceConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SeedSampleDataCommand extends Command
{
    private const OPTION_CUSTOMER_COUNT = 'customer-count';
    private const OPTION_PRODUCT_LIMIT = 'product-limit';
    private const OPTION_VIEWS_PER_CUSTOMER = 'views-per-customer';
    private const OPTION_CARTS_PER_CUSTOMER = 'carts-per-customer';
    private const OPTION_PURCHASES_PER_CUSTOMER = 'purchases-per-customer';
    private const EMAIL_PREFIX = 'recseed+';
    private const EMAIL_DOMAIN = '@fashionstore.local';
    private const ORDER_MARKER = 'rec_seed_';

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('fashionstore:recommendation:seed-sample-data')
            ->setDescription('Seed fake customers and interactions for collaborative filtering')
            ->addOption(self::OPTION_CUSTOMER_COUNT, null, InputOption::VALUE_OPTIONAL, 'How many fake customers to create', 12)
            ->addOption(self::OPTION_PRODUCT_LIMIT, null, InputOption::VALUE_OPTIONAL, 'How many products to include in the seed pool', 50)
            ->addOption(self::OPTION_VIEWS_PER_CUSTOMER, null, InputOption::VALUE_OPTIONAL, 'How many view events per customer', 18)
            ->addOption(self::OPTION_CARTS_PER_CUSTOMER, null, InputOption::VALUE_OPTIONAL, 'How many add-to-cart events per customer', 8)
            ->addOption(self::OPTION_PURCHASES_PER_CUSTOMER, null, InputOption::VALUE_OPTIONAL, 'How many purchases per customer', 4);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $customerCount = max(10, min(20, (int) $input->getOption(self::OPTION_CUSTOMER_COUNT)));
        $productLimit = max(20, min(80, (int) $input->getOption(self::OPTION_PRODUCT_LIMIT)));
        $viewsPerCustomer = max(6, (int) $input->getOption(self::OPTION_VIEWS_PER_CUSTOMER));
        $cartsPerCustomer = max(3, (int) $input->getOption(self::OPTION_CARTS_PER_CUSTOMER));
        $purchasesPerCustomer = max(2, (int) $input->getOption(self::OPTION_PURCHASES_PER_CUSTOMER));

        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();

        try {
            $products = $this->fetchProducts($productLimit);
            if (count($products) < 10) {
                throw new \RuntimeException('Need at least 10 products to seed collaborative filtering data.');
            }

            $customers = $this->upsertFakeCustomers($customerCount);
            $this->deletePreviousSeedData(array_keys($customers));

            $seededViews = 0;
            $seededCarts = 0;
            $seededPurchases = 0;

            $groupCount = 3;
            $groupProductPools = $this->buildGroupProductPools($products, $groupCount);

            foreach (array_values($customers) as $index => $customer) {
                $groupIndex = $index % $groupCount;
                $preferredProducts = $groupProductPools[$groupIndex];
                $secondaryProducts = $groupProductPools[($groupIndex + 1) % $groupCount];

                $viewProducts = $this->pickProducts($preferredProducts, $secondaryProducts, $viewsPerCustomer, 0.75);
                $cartProducts = $this->pickProducts($preferredProducts, $secondaryProducts, $cartsPerCustomer, 0.8);
                $purchaseProducts = $this->pickProducts($preferredProducts, $secondaryProducts, $purchasesPerCustomer, 0.9);

                foreach ($viewProducts as $offset => $product) {
                    $loggedAt = $this->randomTimestamp(60 - $groupIndex * 5, 12 + $offset);
                    $this->insertReportEvent((int) $customer['entity_id'], (int) $product['product_id'], 1, $loggedAt);
                    ++$seededViews;
                }

                foreach ($cartProducts as $offset => $product) {
                    $loggedAt = $this->randomTimestamp(40 - $groupIndex * 3, 6 + $offset);
                    $this->insertReportEvent((int) $customer['entity_id'], (int) $product['product_id'], 4, $loggedAt);
                    ++$seededCarts;
                }

                foreach ($purchaseProducts as $offset => $product) {
                    $createdAt = $this->randomTimestamp(20 - $groupIndex * 2, 2 + $offset);
                    $this->insertSyntheticOrder($customer, $product, $createdAt);
                    ++$seededPurchases;
                }
            }

            $connection->commit();

            $output->writeln('<info>Seed completed.</info>');
            $output->writeln('<comment>Customers: ' . count($customers) . '</comment>');
            $output->writeln('<comment>Products in pool: ' . count($products) . '</comment>');
            $output->writeln('<comment>Views: ' . $seededViews . '</comment>');
            $output->writeln('<comment>Add to cart: ' . $seededCarts . '</comment>');
            $output->writeln('<comment>Purchases: ' . $seededPurchases . '</comment>');

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    private function fetchProducts(int $limit): array
    {
        $connection = $this->resourceConnection->getConnection();
        $query = $connection->select()
            ->from(['p' => $this->resourceConnection->getTableName('catalog_product_entity')], ['product_id' => 'entity_id', 'sku', 'type_id'])
            ->joinLeft(
                ['pet' => $this->resourceConnection->getTableName('eav_entity_type')],
                "pet.entity_type_code = 'catalog_product'",
                []
            )
            ->joinLeft(
                ['name_attr' => $this->resourceConnection->getTableName('eav_attribute')],
                "name_attr.entity_type_id = pet.entity_type_id AND name_attr.attribute_code = 'name'",
                []
            )
            ->joinLeft(
                ['price_attr' => $this->resourceConnection->getTableName('eav_attribute')],
                "price_attr.entity_type_id = pet.entity_type_id AND price_attr.attribute_code = 'price'",
                []
            )
            ->joinLeft(
                ['name_value' => $this->resourceConnection->getTableName('catalog_product_entity_varchar')],
                'name_value.entity_id = p.entity_id AND name_value.attribute_id = name_attr.attribute_id AND name_value.store_id = 0',
                ['name' => 'value']
            )
            ->joinLeft(
                ['price_value' => $this->resourceConnection->getTableName('catalog_product_entity_decimal')],
                'price_value.entity_id = p.entity_id AND price_value.attribute_id = price_attr.attribute_id AND price_value.store_id = 0',
                ['price' => 'value']
            )
            ->where('p.type_id = ?', 'simple')
            ->order('p.entity_id ASC')
            ->limit($limit);

        $rows = $connection->fetchAll($query);

        return array_map(static function (array $row): array {
            $row['product_id'] = (int) $row['product_id'];
            $row['price'] = isset($row['price']) ? (float) $row['price'] : 199000.0;
            $row['name'] = (string) ($row['name'] ?: $row['sku']);

            return $row;
        }, $rows);
    }

    private function upsertFakeCustomers(int $customerCount): array
    {
        $connection = $this->resourceConnection->getConnection();
        $customerTable = $this->resourceConnection->getTableName('customer_entity');
        $emails = [];
        for ($index = 1; $index <= $customerCount; ++$index) {
            $emails[] = $this->buildCustomerEmail($index);
        }

        $existingRows = $connection->fetchAll(
            $connection->select()
                ->from($customerTable, ['entity_id', 'email', 'firstname', 'lastname'])
                ->where('email IN (?)', $emails)
        );

        $customersByEmail = [];
        foreach ($existingRows as $row) {
            $customersByEmail[(string) $row['email']] = $row;
        }

        for ($index = 1; $index <= $customerCount; ++$index) {
            $email = $this->buildCustomerEmail($index);
            if (isset($customersByEmail[$email])) {
                continue;
            }

            $firstname = 'RecSeed' . $index;
            $lastname = 'Customer';
            $connection->insert($customerTable, [
                'website_id' => 1,
                'email' => $email,
                'group_id' => 1,
                'store_id' => 1,
                'created_in' => 'Default Store View',
                'firstname' => $firstname,
                'lastname' => $lastname,
                'is_active' => 1,
                'disable_auto_group_change' => 0,
                'created_at' => $this->randomTimestamp(90, 0),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);

            $customersByEmail[$email] = [
                'entity_id' => (int) $connection->lastInsertId($customerTable),
                'email' => $email,
                'firstname' => $firstname,
                'lastname' => $lastname,
            ];
        }

        return $customersByEmail;
    }

    private function deletePreviousSeedData(array $customerIds): void
    {
        if ($customerIds === []) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $reportEventTable = $this->resourceConnection->getTableName('report_event');
        $salesOrderTable = $this->resourceConnection->getTableName('sales_order');
        $salesOrderItemTable = $this->resourceConnection->getTableName('sales_order_item');

        $seedOrderIds = $connection->fetchCol(
            $connection->select()
                ->from($salesOrderTable, ['entity_id'])
                ->where('ext_order_id LIKE ?', self::ORDER_MARKER . '%')
        );

        if ($seedOrderIds !== []) {
            $connection->delete($salesOrderItemTable, ['order_id IN (?)' => $seedOrderIds]);
            $connection->delete($salesOrderTable, ['entity_id IN (?)' => $seedOrderIds]);
        }

        $connection->delete(
            $reportEventTable,
            [
                'subject_id IN (?)' => $customerIds,
                'event_type_id IN (?)' => [1, 4],
                'subtype = ?' => 0,
            ]
        );
    }

    private function buildGroupProductPools(array $products, int $groupCount): array
    {
        $chunkSize = max(8, (int) ceil(count($products) / $groupCount));
        $chunks = array_chunk($products, $chunkSize);
        $pools = [];

        for ($index = 0; $index < $groupCount; ++$index) {
            $current = $chunks[$index] ?? [];
            $next = $chunks[($index + 1) % count($chunks)] ?? [];
            $overlap = array_slice($next, 0, min(5, count($next)));
            $pools[] = array_values(array_merge($current, $overlap));
        }

        return $pools;
    }

    private function pickProducts(array $preferredProducts, array $secondaryProducts, int $count, float $preferredRatio): array
    {
        $preferredCount = min(count($preferredProducts), max(1, (int) round($count * $preferredRatio)));
        $secondaryCount = max(0, $count - $preferredCount);

        shuffle($preferredProducts);
        shuffle($secondaryProducts);

        $selection = array_slice($preferredProducts, 0, $preferredCount);
        foreach (array_slice($secondaryProducts, 0, $secondaryCount) as $product) {
            $selection[] = $product;
        }

        $unique = [];
        foreach ($selection as $product) {
            $unique[(int) $product['product_id']] = $product;
        }

        return array_values($unique);
    }

    private function insertReportEvent(int $customerId, int $productId, int $eventTypeId, string $loggedAt): void
    {
        $connection = $this->resourceConnection->getConnection();
        $connection->insert($this->resourceConnection->getTableName('report_event'), [
            'logged_at' => $loggedAt,
            'event_type_id' => $eventTypeId,
            'object_id' => $productId,
            'subject_id' => $customerId,
            'subtype' => 0,
            'store_id' => 1,
        ]);
    }

    private function insertSyntheticOrder(array $customer, array $product, string $createdAt): void
    {
        $connection = $this->resourceConnection->getConnection();
        $salesOrderTable = $this->resourceConnection->getTableName('sales_order');
        $salesOrderItemTable = $this->resourceConnection->getTableName('sales_order_item');

        $price = max(99000.0, (float) $product['price']);
        $incrementId = $this->nextIncrementId();
        $extOrderId = self::ORDER_MARKER . bin2hex(random_bytes(6));

        $connection->insert($salesOrderTable, [
            'state' => 'complete',
            'status' => 'complete',
            'protect_code' => substr(hash('sha256', $extOrderId), 0, 32),
            'store_id' => 1,
            'customer_id' => (int) $customer['entity_id'],
            'base_grand_total' => $price,
            'grand_total' => $price,
            'base_subtotal' => $price,
            'subtotal' => $price,
            'base_total_paid' => $price,
            'total_paid' => $price,
            'base_total_due' => 0,
            'total_due' => 0,
            'base_total_qty_ordered' => 1,
            'total_qty_ordered' => 1,
            'customer_is_guest' => 0,
            'customer_group_id' => 1,
            'base_currency_code' => 'VND',
            'global_currency_code' => 'VND',
            'order_currency_code' => 'VND',
            'store_currency_code' => 'VND',
            'customer_email' => (string) $customer['email'],
            'customer_firstname' => (string) $customer['firstname'],
            'customer_lastname' => (string) $customer['lastname'],
            'ext_order_id' => $extOrderId,
            'increment_id' => $incrementId,
            'store_name' => 'Main Website\nDefault Store\nDefault Store View',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'total_item_count' => 1,
        ]);

        $orderId = (int) $connection->lastInsertId($salesOrderTable);

        $connection->insert($salesOrderItemTable, [
            'order_id' => $orderId,
            'store_id' => 1,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'product_id' => (int) $product['product_id'],
            'product_type' => (string) ($product['type_id'] ?: 'simple'),
            'weight' => 1,
            'is_virtual' => 0,
            'sku' => (string) $product['sku'],
            'name' => (string) $product['name'],
            'no_discount' => 1,
            'qty_ordered' => 1,
            'qty_invoiced' => 1,
            'price' => $price,
            'base_price' => $price,
            'row_total' => $price,
            'base_row_total' => $price,
            'row_invoiced' => $price,
            'base_row_invoiced' => $price,
            'price_incl_tax' => $price,
            'base_price_incl_tax' => $price,
            'row_total_incl_tax' => $price,
            'base_row_total_incl_tax' => $price,
        ]);
    }

    private function nextIncrementId(): string
    {
        $connection = $this->resourceConnection->getConnection();
        $salesOrderTable = $this->resourceConnection->getTableName('sales_order');
        $lastIncrementId = (string) $connection->fetchOne(
            $connection->select()
                ->from($salesOrderTable, ['increment_id'])
                ->where('increment_id IS NOT NULL')
                ->order('entity_id DESC')
                ->limit(1)
        );

        $numeric = ctype_digit($lastIncrementId) ? (int) $lastIncrementId : 1;

        return str_pad((string) ($numeric + 1), max(9, strlen($lastIncrementId)), '0', STR_PAD_LEFT);
    }

    private function buildCustomerEmail(int $index): string
    {
        return self::EMAIL_PREFIX . $index . self::EMAIL_DOMAIN;
    }

    private function randomTimestamp(int $maxDaysBack, int $minDaysBack): string
    {
        $daysBack = mt_rand(max(0, $minDaysBack), max($minDaysBack, $maxDaysBack));
        $hours = mt_rand(0, 23);
        $minutes = mt_rand(0, 59);
        $seconds = mt_rand(0, 59);

        return gmdate('Y-m-d H:i:s', strtotime(sprintf('-%d days -%d hours -%d minutes -%d seconds', $daysBack, $hours, $minutes, $seconds)));
    }
}