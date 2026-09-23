<?php

declare(strict_types=1);

/**
 * Controleert de twee tijdslot-scenario's van de ticketpagina (/tickets).
 *
 * Happy flow:  per editie een overzicht van alle tijdsloten, gegroepeerd per
 *              dag, met starttijd, prijs en beschikbare tickets t.o.v. het totaal.
 * Unhappy flow: een editie zonder tijdsloten geeft nul rijen; de controller
 *              toont dan "Er zijn momenteel geen tijdsloten beschikbaar."
 *
 * Voor het happy-deel worden de tijdsloten van één evenement tijdelijk
 * verborgen en daarna altijd weer aangezet.
 *
 * Gebruik: php database/test-tijdsloten.php [event-id]
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;
use App\Models\TijdslotModel;

$evenementId = (int) ($argv[1] ?? 4);
$model = new TijdslotModel();

/**
 * Zet de tijdsloten van één evenement aan of uit en geeft het aantal
 * gewijzigde rijen terug.
 */
function zetTijdsloten(int $id, int $isActief): int
{
    $statement = Database::verbinding()->prepare('CALL sp_demo_zichtbaarheid_tijdsloten(?, ?)');
    $statement->execute([$id, $isActief]);
    $rij = $statement->fetch();
    $statement->closeCursor();

    return (int) ($rij['aantal_gewijzigd'] ?? 0);
}

$fouten = [];

// --- Happy flow -------------------------------------------------------------
$alle = $model->haalOverzicht(0);
$vanEvenement = $model->haalOverzicht($evenementId);
$perDag = $model->haalGegroepeerdPerDag($evenementId);

echo 'Tijdsloten totaal    : ' . count($alle) . PHP_EOL;
echo 'Tijdsloten event ' . $evenementId . '   : ' . count($vanEvenement) . PHP_EOL;
echo 'Dagen met tijdsloten : ' . count($perDag) . PHP_EOL;

if ($alle === []) {
    $fouten[] = 'Geen tijdsloten gevonden: de happy flow kan niets tonen.';
}

foreach ($vanEvenement as $tijdslot) {
    if ($tijdslot->tarief <= 0) {
        $fouten[] = 'Tijdslot ' . $tijdslot->tijdslot . ' heeft geen geldige prijs.';
        break;
    }

    if ($tijdslot->beschikbareTickets < 0 || $tijdslot->beschikbareTickets > $tijdslot->capaciteit) {
        $fouten[] = 'Tijdslot ' . $tijdslot->tijdslot
            . ' heeft ' . $tijdslot->beschikbareTickets . ' van '
            . $tijdslot->capaciteit . ' tickets beschikbaar.';
        break;
    }
}

// De groepering per dag moet exact dezelfde rijen opleveren als het overzicht.
$aantalInGroepen = array_sum(array_map('count', $perDag));
if ($aantalInGroepen !== count($vanEvenement)) {
    $fouten[] = 'Groepeeren per dag geeft ' . $aantalInGroepen
        . ' tijdsloten, terwijl het overzicht er ' . count($vanEvenement) . ' heeft.';
}

// --- Unhappy flow -----------------------------------------------------------
try {
    $uitGezet = zetTijdsloten($evenementId, 0);
    $zonder = $model->haalGegroepeerdPerDag($evenementId);

    echo 'Tijdsloten na uitzetten : ' . count($zonder) . PHP_EOL;

    if ($zonder !== []) {
        $fouten[] = 'Zonder tijdsloten moeten er 0 rijen komen (unhappy flow), nu '
            . count($zonder) . '.';
    }
} finally {
    // Altijd terugzetten, ook als de controle faalt.
    zetTijdsloten($evenementId, 1);
    $hersteld = count($model->haalOverzicht($evenementId));

    echo 'Tijdsloten na herstellen: ' . $hersteld . PHP_EOL;

    if ($hersteld !== count($vanEvenement)) {
        $fouten[] = 'Na het herstellen staan er ' . $hersteld
            . ' tijdsloten in plaats van ' . count($vanEvenement) . '.';
    }
}

// --- Resultaat --------------------------------------------------------------
if ($fouten === []) {
    echo PHP_EOL . 'OK: de tijdslot-scenario\'s gedragen zich zoals verwacht.' . PHP_EOL;
    echo 'Toon de unhappy flow met: php database/demo_unhappy.php uit tijdsloten '
        . $evenementId . PHP_EOL;

    exit(0);
}

foreach ($fouten as $fout) {
    echo 'FOUT: ' . $fout . PHP_EOL;
}

exit(1);
