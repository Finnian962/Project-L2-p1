<?php

declare(strict_types=1);

/**
 * Installatiescript voor de database.
 *
 * Voert achtereenvolgens uit:
 *   1. 01_schema.sql            (database en tabellen)
 *   2. 02_stored_procedures.sql (alle read-procedures)
 *   3. 03_seed.sql              (demodata)
 *
 * Gebruik vanaf de commandline:
 *   php database/install.php
 *
 * Het script leest de databasegegevens uit config/config.php, maar maakt
 * verbinding zonder databasenaam: het schemabestand maakt de database zelf aan.
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Config;
use App\Core\Logger;

/**
 * Splitst een SQL-bestand in losse statements.
 *
 * Houdt rekening met commentaar, quotes en het DELIMITER-commando dat nodig is
 * voor stored procedures met meerdere statements in de body.
 *
 * @return list<string>
 */
function splitsSqlStatements(string $sql): array
{
    $statements = [];
    $huidig = '';
    $scheidingsteken = ';';
    $lengte = strlen($sql);
    $positie = 0;

    while ($positie < $lengte) {
        $teken = $sql[$positie];
        $rest = substr($sql, $positie);

        // Regelcommentaar: -- of #
        if ($huidig === '' || str_ends_with($huidig, "\n")) {
            if (preg_match('/^(--[^\n]*|#[^\n]*)\n?/', $rest, $treffer) === 1) {
                $positie += strlen($treffer[0]);
                continue;
            }

            // Nieuw scheidingsteken instellen (DELIMITER $$).
            if (preg_match('/^DELIMITER[ \t]+(\S+)[ \t]*\r?\n/i', $rest, $treffer) === 1) {
                $scheidingsteken = $treffer[1];
                $positie += strlen($treffer[0]);
                continue;
            }
        }

        // Tekst tussen quotes ongemoeid laten.
        if ($teken === "'" || $teken === '"' || $teken === '`') {
            $eind = $positie + 1;

            while ($eind < $lengte) {
                if ($sql[$eind] === '\\') {
                    $eind += 2;
                    continue;
                }

                if ($sql[$eind] === $teken) {
                    break;
                }

                $eind++;
            }

            $huidig .= substr($sql, $positie, $eind - $positie + 1);
            $positie = $eind + 1;
            continue;
        }

        // Einde van een statement bereikt?
        if (str_starts_with($rest, $scheidingsteken)) {
            $statement = trim($huidig);

            if ($statement !== '') {
                $statements[] = $statement;
            }

            $huidig = '';
            $positie += strlen($scheidingsteken);
            continue;
        }

        $huidig .= $teken;
        $positie++;
    }

    $statement = trim($huidig);

    if ($statement !== '') {
        $statements[] = $statement;
    }

    return $statements;
}

/**
 * Voert één SQL-bestand uit op de gegeven verbinding.
 */
function voerBestandUit(PDO $verbinding, string $bestandspad): int
{
    $sql = file_get_contents($bestandspad);

    if ($sql === false) {
        throw new RuntimeException('Kan bestand niet lezen: ' . $bestandspad);
    }

    $statements = splitsSqlStatements($sql);

    foreach ($statements as $statement) {
        $verbinding->exec($statement);
    }

    return count($statements);
}

$bestanden = [
    __DIR__ . '/01_schema.sql',
    __DIR__ . '/02_stored_procedures.sql',
    __DIR__ . '/03_seed.sql',
];

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%d;charset=%s',
        (string) Config::get('database.host', '127.0.0.1'),
        (int) Config::get('database.port', 3306),
        (string) Config::get('database.charset', 'utf8mb4')
    );

    $verbinding = new PDO(
        $dsn,
        (string) Config::get('database.gebruiker', 'root'),
        (string) Config::get('database.wachtwoord', ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    foreach ($bestanden as $bestand) {
        $aantal = voerBestandUit($verbinding, $bestand);
        echo sprintf("OK  %-28s (%d statements)%s", basename($bestand), $aantal, PHP_EOL);
        Logger::info('SQL-bestand uitgevoerd', ['bestand' => basename($bestand), 'statements' => $aantal]);
    }

    echo PHP_EOL . 'De database "sneakerness" is klaar voor gebruik.' . PHP_EOL;
} catch (Throwable $fout) {
    Logger::exception($fout, 'Installatie van de database mislukt');

    echo 'FOUT: ' . $fout->getMessage() . PHP_EOL;
    echo 'Controleer of MySQL draait en of de gegevens in config/config.php kloppen.' . PHP_EOL;

    exit(1);
}
