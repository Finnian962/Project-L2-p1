<?php

declare(strict_types=1);

/**
 * Hulpscript voor de sprintreview.
 *
 * Zet alle events tijdelijk op inactief om de unhappy flow van het
 * eventoverzicht te tonen ("Er zijn nog geen events aangemaakt."), of weer
 * op actief voor de happy flow.
 *
 * Gebruik:
 *   php database/demo_unhappy.php uit   -> geen events zichtbaar (unhappy flow)
 *   php database/demo_unhappy.php aan   -> alle events weer zichtbaar (happy flow)
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;
use App\Core\Logger;

$keuze = strtolower($argv[1] ?? '');

if (!in_array($keuze, ['aan', 'uit'], true)) {
    echo 'Gebruik: php database/demo_unhappy.php [aan|uit]' . PHP_EOL;

    exit(1);
}

$isActief = $keuze === 'aan' ? 1 : 0;

try {
    $statement = Database::verbinding()->prepare('CALL sp_demo_zichtbaarheid_events(?)');
    $statement->execute([$isActief]);
    $resultaat = $statement->fetch();
    $statement->closeCursor();

    Logger::info('Zichtbaarheid van events gewijzigd via demoscript', [
        'is_actief' => $isActief,
        'rijen'     => $resultaat['aantal_gewijzigd'] ?? 0,
    ]);

    echo $isActief === 1
        ? 'Alle events staan weer op actief (happy flow).' . PHP_EOL
        : 'Alle events staan op inactief (unhappy flow).' . PHP_EOL;
} catch (Throwable $fout) {
    Logger::exception($fout, 'Demoscript kon de zichtbaarheid niet wijzigen');

    echo 'FOUT: ' . $fout->getMessage() . PHP_EOL;

    exit(1);
}
