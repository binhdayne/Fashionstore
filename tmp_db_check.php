<?php
declare(strict_types=1);

$candidates = [
    ['mysql:host=127.0.0.1;port=3306;dbname=magento', 'magento', 'magento'],
    ['mysql:host=localhost;port=3306;dbname=magento', 'magento', 'magento'],
    ['mysql:host=127.0.0.1;port=3306;dbname=magento', 'root', 'magento'],
    ['mysql:host=localhost;port=3306;dbname=magento', 'root', 'magento'],
];

$pdo = null;
foreach ($candidates as [$dsn, $user, $pass]) {
    try {
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "db-ok {$dsn} {$user}\n";
        break;
    } catch (Throwable $e) {
        echo "db-fail {$dsn} {$user}: " . $e->getMessage() . "\n";
    }
}

if (!$pdo) {
    exit(1);
}

$tables = ['quote', 'sales_order'];
foreach ($tables as $table) {
    echo "table: {$table}\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM {$table} LIKE 'fs_delivery_%'");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        echo "none\n";
        continue;
    }

    foreach ($rows as $row) {
        echo $row['Field'] . '|' . $row['Type'] . "\n";
    }
}
