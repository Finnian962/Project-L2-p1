<?php
declare(strict_types=1);
$pdo = new PDO("mysql:host=127.0.0.1;dbname=sneakerness;charset=utf8mb4", "root", "");
$rows = $pdo->query("SELECT id, naam, datum FROM evenement")->fetchAll(PDO::FETCH_ASSOC);
echo "Events: " . count($rows) . "\n";
foreach ($rows as $r) { echo "- {$r['naam']}\n"; }
