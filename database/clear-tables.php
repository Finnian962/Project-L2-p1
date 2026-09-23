<?php
declare(strict_types=1);
$dsn = "mysql:host=127.0.0.1;port=3306;dbname=sneakerness;charset=utf8mb4";
$pdo = new PDO($dsn, "root", "", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    $pdo->exec("TRUNCATE TABLE `$table`");
    echo "Truncated: $table\n";
}
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
echo "\nAlle tabellen leeg.\n";
