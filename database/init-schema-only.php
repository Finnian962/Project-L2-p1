<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

use App\Core\Config;
use App\Core\Logger;

$files = [
    __DIR__ . '/01_schema.sql',
    __DIR__ . '/02_stored_procedures.sql',
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

    foreach ($files as $bestand) {
        $sql = file_get_contents($bestand);
        if ($sql === false) {
            throw new RuntimeException('Kan bestand niet lezen: ' . $bestand);
        }
        $statements = splitsSqlStatements($sql);
        foreach ($statements as $statement) {
            $verbinding->exec($statement);
        }
        echo "OK  " . basename($bestand) . " (" . count($statements) . " statements)\n";
    }
    echo "\nSchema en procedures klaar (geen seed-data).\n";
} catch (Throwable $fout) {
    echo "FOUT: " . $fout->getMessage() . "\n";
    exit(1);
}

function splitsSqlStatements(string $sql): array {
    $statements = [];
    $huidig = '';
    $scheidingsteken = ';';
    $lengte = strlen($sql);
    $positie = 0;
    while ($positie < $lengte) {
        $teken = $sql[$positie];
        $rest = substr($sql, $positie);
        if ($huidig === '' || str_ends_with($huidig, "\n")) {
            if (preg_match('/^(--[^\n]*|#[^\n]*)\n?/', $rest, $treffer) === 1) {
                $positie += strlen($treffer[0]);
                continue;
            }
            if (preg_match('/^DELIMITER[ \t]+(\S+)[ \t]*\r?\n/i', $rest, $treffer) === 1) {
                $scheidingsteken = $treffer[1];
                $positie += strlen($treffer[0]);
                continue;
            }
        }
        if ($teken === "'" || $teken === '"' || $teken === '`') {
            $eind = $positie + 1;
            while ($eind < $lengte) {
                if ($sql[$eind] === '\\') { $eind += 2; continue; }
                if ($sql[$eind] === $teken) { break; }
                $eind++;
            }
            $huidig .= substr($sql, $positie, $eind - $positie + 1);
            $positie = $eind + 1;
            continue;
        }
        if (str_starts_with($rest, $scheidingsteken)) {
            $statement = trim($huidig);
            if ($statement !== '') { $statements[] = $statement; }
            $huidig = '';
            $positie += strlen($scheidingsteken);
            continue;
        }
        $huidig .= $teken;
        $positie++;
    }
    $statement = trim($huidig);
    if ($statement !== '') { $statements[] = $statement; }
    return $statements;
}
