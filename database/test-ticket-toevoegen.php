<?php

declare(strict_types=1);

/**
 * Controleert de scenario's van de twee schrijfacties op de ticketpagina:
 *
 *  - "Ticket toevoegen" (/tickets/toevoegen): een nieuw tijdslot aanmaken.
 *      happy   -> het tijdslot komt in de tijdslotlijst van de editie met de
 *                 capaciteit van de editie, zodat er tickets voor verkocht
 *                 kunnen worden.
 *      unhappy -> een dubbel tijdstip en een dag vóór de editie worden
 *                 geweigerd; er wordt niets opgeslagen.
 *  - "Ticket boeken" (/tickets/boeken): een ticket op een tijdslot boeken.
 *      happy   -> de beschikbaarheid van dat tijdslot gaat omlaag.
 *      unhappy -> meer tickets dan beschikbaar wordt geweigerd zonder effect.
 *
 * Alles wat deze test aanmaakt wordt na afloop weer opgeruimd.
 *
 * Gebruik: php database/test-ticket-toevoegen.php
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;
use App\Core\DatabaseException;
use App\Models\EvenementModel;
use App\Models\TicketModel;
use App\Models\TijdslotModel;

$tickets = new TicketModel();

/** @var list<string> $fouten */
$fouten = [];

/** Zoekt een tijdstip op de gekozen dag dat nog vrij is. */
function vrijTijdslot(array $bezet): string
{
    foreach (['23:00', '23:30', '00:30', '05:45'] as $kandidaat) {
        if (!in_array($kandidaat, $bezet, true)) {
            return $kandidaat;
        }
    }

    return '';
}

$evenement = (new EvenementModel())->haalOverzicht()[0] ?? null;

if ($evenement === null) {
    echo 'FOUT: er is geen actieve editie om een tijdslot aan toe te voegen.' . PHP_EOL;
    exit(1);
}

$bezet = [];

foreach ((new TijdslotModel())->haalOverzicht($evenement->id) as $tijdslot) {
    if ($tijdslot->datum === $evenement->datum) {
        $bezet[] = tijdKort($tijdslot->tijdslot);
    }
}

$tijd = vrijTijdslot($bezet);
$tarief = 17.50;
$prijsId = 0;
$van = (new TijdslotModel())->haalOverzicht($evenement->id);

echo 'Editie              : #' . $evenement->id . ' ' . $evenement->naam . PHP_EOL;
echo 'Dag                 : ' . $evenement->datum . PHP_EOL;
echo 'Nieuw tijdstip      : ' . $tijd . ' voor ' . euro($tarief) . PHP_EOL;
echo 'Tijdsloten ervoor    : ' . count($van) . PHP_EOL;

try {
    // --- Unhappy flow: een dag vóór de editie ------------------------------
    try {
        $tickets->nieuwTijdslot($evenement->id, '2000-01-01', '23:00', $tarief, null);
        $fouten[] = 'Een dag vóór de editie werd geaccepteerd; er had een fout moeten komen.';
    } catch (DatabaseException) {
        echo 'Verwachte fout      : dag vóór de editie geweigerd' . PHP_EOL;
    }

    // --- Happy flow: nieuw tijdslot ----------------------------------------
    $prijsId = $tickets->nieuwTijdslot(
        $evenement->id,
        $evenement->datum,
        $tijd,
        $tarief,
        'Toegevoegd door de test'
    );

    $na = (new TijdslotModel())->haalOverzicht($evenement->id);
    $nieuw = null;

    foreach ($na as $tijdslot) {
        if ($tijdslot->prijsId === $prijsId) {
            $nieuw = $tijdslot;
            break;
        }
    }

    echo 'Tijdslotnummer      : #' . $prijsId . PHP_EOL;
    echo 'Tijdsloten erna     : ' . count($na) . PHP_EOL;

    if ($prijsId <= 0) {
        $fouten[] = 'Er is geen tijdslotnummer teruggegeven.';
    }

    if (count($na) !== count($van) + 1) {
        $fouten[] = 'Het nieuwe tijdslot verschijnt niet op de ticketpagina ('
            . count($van) . ' -> ' . count($na) . ').';
    }

    if ($nieuw === null) {
        $fouten[] = 'Het nieuwe tijdslot staat niet in de tijdsloten van de editie.';
    } else {
        echo 'Beschikbaar         : ' . $nieuw->beschikbareTickets
            . ' van ' . $nieuw->capaciteit . PHP_EOL;

        if ($nieuw->beschikbareTickets !== $nieuw->capaciteit) {
            $fouten[] = 'Een nieuw tijdslot begint niet vol ('
                . $nieuw->beschikbareTickets . ' van ' . $nieuw->capaciteit . ').';
        }

        if ($nieuw->tarief !== $tarief) {
            $fouten[] = 'Het tarief is ' . $nieuw->tarief . ' in plaats van ' . $tarief . '.';
        }
    }

    // --- Unhappy flow: hetzelfde tijdstip nog een keer ---------------------
    try {
        $tickets->nieuwTijdslot($evenement->id, $evenement->datum, $tijd, $tarief, null);
        $fouten[] = 'Een dubbel tijdstip werd geaccepteerd; er had een fout moeten komen.';
    } catch (DatabaseException) {
        echo 'Verwachte fout      : dubbel tijdstip geweigerd' . PHP_EOL;
    }

    $dubbel = 0;

    foreach ((new TijdslotModel())->haalOverzicht($evenement->id) as $tijdslot) {
        if ($tijdslot->prijsId === $prijsId || tijdKort($tijdslot->tijdslot) === $tijd) {
            $dubbel++;
        }
    }

    if ($dubbel !== 1) {
        $fouten[] = 'Er staan ' . $dubbel . ' keer een tijdslot op ' . $tijd . '.';
    }

    // --- Happy flow: ticket boeken op het nieuwe tijdslot ------------------
    $beschikbaar = $nieuw?->beschikbareTickets ?? 0;
    $boeking = $tickets->boekTicket($evenement->id, $prijsId, 2);
    $nieuw = null;

    foreach ((new TijdslotModel())->haalOverzicht($evenement->id) as $tijdslot) {
        if ($tijdslot->prijsId === $prijsId) {
            $nieuw = $tijdslot;
        }
    }

    echo 'Ticketnummer        : #' . $boeking . PHP_EOL;
    echo 'Beschikbaar na boeking: ' . ($nieuw?->beschikbareTickets ?? -1) . PHP_EOL;

    if ($boeking <= 0) {
        $fouten[] = 'Er is geen ticketnummer teruggegeven bij het boeken.';
    }

    if ($nieuw === null || $nieuw->beschikbareTickets !== $beschikbaar - 2) {
        $fouten[] = 'De beschikbaarheid ging van ' . $beschikbaar . ' naar '
            . ($nieuw?->beschikbareTickets ?? 'onbekend') . ', terwijl dat '
            . ($beschikbaar - 2) . ' moest zijn.';
    }

    // --- Unhappy flow: meer tickets dan beschikbaar ------------------------
    try {
        $tickets->boekTicket($evenement->id, $prijsId, $beschikbaar + 5);
        $fouten[] = 'Meer tickets dan beschikbaar werd geaccepteerd; er had een fout moeten komen.';
    } catch (DatabaseException) {
        echo 'Verwachte fout      : te veel tickets geweigerd' . PHP_EOL;
    }

    $over = null;

    foreach ((new TijdslotModel())->haalOverzicht($evenement->id) as $tijdslot) {
        if ($tijdslot->prijsId === $prijsId) {
            $over = $tijdslot->beschikbareTickets;
        }
    }

    if ($over !== $beschikbaar - 2) {
        $fouten[] = 'Bij een fout is de beschikbaarheid toch veranderd ('
            . ($beschikbaar - 2) . ' -> ' . $over . ').';
    }
} finally {
    // Alles wat deze test maakte nooit in de demodata achterlaten.
    if ($prijsId > 0) {
        $pdo = Database::verbinding();

        $verwijderTickets = $pdo->prepare('DELETE FROM ticket WHERE prijs_id = ?');
        $verwijderTickets->execute([$prijsId]);

        $verwijderSlot = $pdo->prepare('DELETE FROM prijs WHERE id = ?');
        $verwijderSlot->execute([$prijsId]);

        echo 'Opgeschoond         : ' . $verwijderTickets->rowCount()
            . ' ticket(s) en ' . $verwijderSlot->rowCount() . ' tijdslot verwijderd' . PHP_EOL;
    }

    $hersteld = (new TijdslotModel())->haalOverzicht($evenement->id);
    echo 'Tijdsloten na herstel: ' . count($hersteld) . PHP_EOL;

    if (count($hersteld) !== count($van)) {
        $fouten[] = 'Na het opschonen staan er ' . count($hersteld)
            . ' tijdsloten in plaats van ' . count($van) . '.';
    }
}

// --- Resultaat --------------------------------------------------------------
if ($fouten === []) {
    echo PHP_EOL . 'OK: een nieuw tijdslot komt op de ticketpagina en het boeken '
        . 'van tickets telt mee in de beschikbaarheid.' . PHP_EOL;

    exit(0);
}

foreach ($fouten as $fout) {
    echo 'FOUT: ' . $fout . PHP_EOL;
}

exit(1);
