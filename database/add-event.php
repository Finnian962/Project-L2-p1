<?php
declare(strict_types=1);
$pdo = new PDO("mysql:host=127.0.0.1;dbname=sneakerness;charset=utf8mb4", "root", "");
$pdo->exec("INSERT INTO evenement (naam, datum, locatie, aantal_tickets_per_tijdslot, beschikbare_stands) VALUES ('Sneakerness Rotterdam 2026', '2026-10-14', 'Van Nellefabriek, Rotterdam', 250, 30)");
echo "Evenement toegevoegd!\n";
