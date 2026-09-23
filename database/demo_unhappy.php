<?php

declare(strict_types=1);

/**
 * Hulpscript voor de sprintreview.
 *
 * Zet tijdelijk data op inactief om een unhappy flow te tonen, of weer op
 * actief voor de happy flow.
 *
 * Gebruik:
 *   php database/demo_unhappy.php uit              -> geen events zichtbaar
 *                                                      (unhappy flow eventoverzicht)
 *   php database/demo_unhappy.php aan              -> alle events weer zichtbaar
 *
 *   php database/demo_unhappy.php uit tijdsloten   -> geen tijdsloten voor
 *                                                      alle evenementen
 *   php database/demo_unhappy.php uit tijdsloten 5 -> geen tijdsloten voor
 *                                                      evenement 5 (unhappy flow
 *                                                      ticketpagina)
 *   php database/demo_unhappy.php aan tijdsloten   -> tijdsloten weer zichtbaar
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;
use App\Core\Logger;

$keuze   = strtolower($argv[1] ?? '');
$wat     = strtolower($argv[2] ?? 'events');
$evenementId = isset($argv[3]) ? (int) $argv[3] : 0;

if (!in_array($keuze, ['aan', 'uit'], true)) {
    echo 'Gebruik: php database/demo_unhappy.php [aan|uit] [events|tijdsloten] [event-id]' . PHP_EOL;

    exit(1);
}

if (!in_array($wat, ['events', 'tijdsloten'], true)) {
    echo 'Gebruik: php database/demo_unhappy.php [aan|uit] [events|tijdsloten] [event-id]' . PHP_EOL;

    exit(1);
}

$isActief = $keuze === 'aan' ? 1 : 0;

try {
    if ($wat === 'tijdsloten') {
        $statement = Database::verbinding()->prepare('CALL sp_demo_zichtbaarheid_tijdsloten(?, ?)');
        $statement->execute([$evenementId, $isActief]);
        $welke = $evenementId === 0 ? 'van alle evenementen' : 'van evenement ' . $evenementId;
    } else {
        $statement = Database::verbinding()->prepare('CALL sp_demo_zichtbaarheid_events(?)');
        $statement->execute([$isActief]);
        $welke = 'events';
    }

    $resultaat = $statement->fetch();
    $statement->closeCursor();

    Logger::info('Zichtbaarheid gewijzigd via demoscript', [
        'onderwerp'  => $wat,
        'is_actief'  => $isActief,
        'evenement'  => $evenementId,
        'rijen'      => $resultaat['aantal_gewijzigd'] ?? 0,
    ]);

    if ($wat === 'tijdsloten') {
        echo $isActief === 1
            ? 'De tijdsloten ' . $welke . ' staan weer op actief (happy flow).' . PHP_EOL
            : 'De tijdsloten ' . $welke . ' staan op inactief (unhappy flow).' . PHP_EOL;
    } else {
        echo $isActief === 1
            ? 'Alle events staan weer op actief (happy flow).' . PHP_EOL
            : 'Alle events staan op inactief (unhappy flow).' . PHP_EOL;
    }
} catch (Throwable $fout) {
    Logger::exception($fout, 'Demoscript kon de zichtbaarheid niet wijzigen');

    echo 'FOUT: ' . $fout->getMessage() . PHP_EOL;

    exit(1);
}
