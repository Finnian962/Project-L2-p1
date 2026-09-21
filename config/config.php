<?php

declare(strict_types=1);

/**
 * Centrale configuratie van de Sneakerness-webapplicatie.
 *
 * Pas hier de databasegegevens aan wanneer je een andere omgeving gebruikt
 * (bijvoorbeeld een ander wachtwoord voor de root-gebruiker van WAMP/XAMPP).
 */

return [
    'app' => [
        'naam'        => 'Sneakerness®',
        'omgeving'    => 'development',
        'toon_fouten' => true,
    ],

    'database' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'naam'     => 'sneakerness',
        'gebruiker' => 'root',
        'wachtwoord' => '',
        'charset'  => 'utf8mb4',
    ],

    'log' => [
        // Map waarin de technische log wordt weggeschreven.
        'map'    => __DIR__ . '/../storage/logs',
        // Laagste niveau dat gelogd wordt: debug, info, warning of error.
        'niveau' => 'debug',
    ],
];
