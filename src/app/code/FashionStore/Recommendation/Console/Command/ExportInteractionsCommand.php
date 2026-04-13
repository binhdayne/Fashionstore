<?php

namespace FashionStore\Recommendation\Console\Command;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportInteractionsCommand extends Command
{
    private const OPTION_OUTPUT = 'output';
    private const OPTION_LIMIT = 'limit';

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly DirectoryList $directoryList,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('fashionstore:recommendation:export-interactions')
            ->setDescription('Export behavioral interaction data for recommendation model training')
            ->addOption(
                self::OPTION_OUTPUT,
                null,
                InputOption::VALUE_REQUIRED,
                'Relative or absolute output CSV path',
                'var/export/recommendation_interactions.csv'
            )
            ->addOption(
                self::OPTION_LIMIT,
                null,
                InputOption::VALUE_OPTIONAL,
                'Optional LIMIT clause for debugging exports'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = $this->resourceConnection->getConnection();
        $reportEventTable = $this->resourceConnection->getTableName('report_event');
        $salesOrderTable = $this->resourceConnection->getTableName('sales_order');
        $salesOrderItemTable = $this->resourceConnection->getTableName('sales_order_item');

        $sql = sprintf(
            "SELECT user_id, product_id, interaction_type, timestamp
            FROM (
                SELECT
                    CASE WHEN subtype = 0
                        THEN CONCAT('customer:', subject_id)
                        ELSE CONCAT('visitor:', subject_id)
                    END AS user_id,
                    object_id AS product_id,
                    CASE event_type_id
                        WHEN 1 THEN 'view'
                        WHEN 4 THEN 'add_to_cart'
                    END AS interaction_type,
                    logged_at AS timestamp
                FROM %s
                WHERE event_type_id IN (1, 4)
                    AND object_id > 0
                    AND subject_id > 0

                UNION ALL

                SELECT
                    CASE
                        WHEN so.customer_id IS NOT NULL THEN CONCAT('customer:', so.customer_id)
                        WHEN so.customer_email IS NOT NULL AND so.customer_email <> '' THEN CONCAT('guest:', so.customer_email)
                        ELSE CONCAT('order:', so.increment_id)
                    END AS user_id,
                    soi.product_id AS product_id,
                    'purchase' AS interaction_type,
                    so.created_at AS timestamp
                FROM %s AS so
                INNER JOIN %s AS soi ON soi.order_id = so.entity_id
                WHERE soi.parent_item_id IS NULL
                    AND soi.product_id IS NOT NULL
                    AND so.state <> 'canceled'
            ) AS interactions
            ORDER BY timestamp DESC",
            $reportEventTable,
            $salesOrderTable,
            $salesOrderItemTable
        );

        $limit = (int) $input->getOption(self::OPTION_LIMIT);
        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }

        $rows = $connection->fetchAll($sql);
        $outputPath = $this->resolveOutputPath((string) $input->getOption(self::OPTION_OUTPUT));
        $this->writeCsv($outputPath, $rows);

        $output->writeln('<info>Exported ' . count($rows) . ' interactions to ' . $outputPath . '</info>');
        $output->writeln('<comment>Schema: user_id, product_id, interaction_type, timestamp</comment>');

        return Command::SUCCESS;
    }

    private function resolveOutputPath(string $requestedPath): string
    {
        if ($requestedPath === '') {
            $requestedPath = 'var/export/recommendation_interactions.csv';
        }

        if ($requestedPath[0] === '/') {
            return $requestedPath;
        }

        return $this->directoryList->getRoot() . '/' . ltrim($requestedPath, '/');
    }

    private function writeCsv(string $outputPath, array $rows): void
    {
        $directory = dirname($outputPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $handle = fopen($outputPath, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open export file: ' . $outputPath);
        }

        fputcsv($handle, ['user_id', 'product_id', 'interaction_type', 'timestamp']);

        foreach ($rows as $row) {
            fputcsv($handle, [
                (string) ($row['user_id'] ?? ''),
                (int) ($row['product_id'] ?? 0),
                (string) ($row['interaction_type'] ?? ''),
                (string) ($row['timestamp'] ?? ''),
            ]);
        }

        fclose($handle);
    }
}