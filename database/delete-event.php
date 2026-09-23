<?php
declare(strict_types=1);
$pdo = new PDO("mysql:host=127.0.0.1;dbname=sneakerness;charset=utf8mb4", "root", "");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
$count = $pdo->exec("DELETE FROM evenement");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
echo "Verwijderd: $count event(en)\n";
