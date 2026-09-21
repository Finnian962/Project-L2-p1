<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/**
 * Beheert de (enige) PDO-verbinding met de MySQL-database.
 *
 * De verbinding wordt pas opgebouwd wanneer die nodig is en daarna hergebruikt.
 */
final class Database
{
    private static ?PDO $verbinding = null;

    /**
     * Geeft de actieve PDO-verbinding terug en maakt deze zo nodig aan.
     *
     * @throws DatabaseException Wanneer de verbinding niet tot stand komt.
     */
    public static function verbinding(): PDO
    {
        if (self::$verbinding instanceof PDO) {
            return self::$verbinding;
        }

        $host       = (string) Config::get('database.host', '127.0.0.1');
        $poort      = (int) Config::get('database.port', 3306);
        $naam       = (string) Config::get('database.naam', 'sneakerness');
        $gebruiker  = (string) Config::get('database.gebruiker', 'root');
        $wachtwoord = (string) Config::get('database.wachtwoord', '');
        $charset    = (string) Config::get('database.charset', 'utf8mb4');

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $poort, $naam, $charset);

        try {
            self::$verbinding = new PDO($dsn, $gebruiker, $wachtwoord, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);

            Logger::debug('Databaseverbinding opgezet', ['dsn' => $dsn, 'gebruiker' => $gebruiker]);

            return self::$verbinding;
        } catch (PDOException $fout) {
            // Technische details alleen naar de log, niet naar het scherm.
            Logger::error('Databaseverbinding mislukt', [
                'dsn'      => $dsn,
                'sqlstate' => $fout->getCode(),
                'melding'  => $fout->getMessage(),
            ]);

            throw new DatabaseException(
                'De verbinding met de database is mislukt. Probeer het later opnieuw.',
                0,
                $fout
            );
        }
    }
}
