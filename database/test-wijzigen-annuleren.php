<?php

declare(strict_types=1);

/**
 * Controleert het wijzigen en annuleren van een bestelling (user stories "Als
 * bezoeker wil ik na aankoop mijn tickets kunnen wijzigen" en "Als bezoeker
 * wil ik mijn tickets kunnen annuleren"):
 *
 *  - happy  -> bestelling ophalen met e-mailadres en ticketnummer, aantal
 *              wijzigen (303, nieuwe bestelgegevens en prijsverschil op de
 *              bevestiging) en daarna annuleren (303, "De annulering is
 *              gelukt." met het terugbetaalde bedrag); de tickets worden
 *              ongeldig en de beschikbaarheid gaat weer omhoog.
 *  - unhappy -> ongeldige invoer (422), een onbekende bestelling (404), meer
 *              tickets dan beschikbaar (422), een verstreken termijn ("Je
 *              bestelling kan niet meer worden gewijzigd/geannuleerd.",
 *              422, niets veranderd), een dubbele annulering (422) en een
 *              technische fout, bijv. de database niet bereikbaar (503).
 *
 * Het script heeft de developmentserver nodig:
 *     php -S 127.0.0.1:8000 server.php
 *
 * Alles wat deze test aanmaakt wordt na afloop weer opgeruimd.
 *
 * Gebruik: php database/test-wijzigen-annuleren.php
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;
use App\Models\TijdslotModel;

const BASIS_URL = 'http://127.0.0.1:8000';
const CONFIGURATIE = __DIR__ . '/../config/config.php';

/** @var list<string> $fouten */
$fouten = [];

/** @var list<int> $aangemaakteTickets */
$aangemaakteTickets = [];

$eigenTestEmail = 'wijzigtest-' . bin2hex(random_bytes(4)) . '@voorbeeld.nl';

// Ook bij een onverwachte stop wordt de testdata opgeruimd.
register_shutdown_function(static function () use (&$aangemaakteTickets, $eigenTestEmail): void {
    try {
        $verbinding = Database::verbinding();

        if ($aangemaakteTickets !== []) {
            $ids = implode(', ', array_filter($aangemaakteTickets));

            if ($ids !== '') {
                $verbinding->exec('DELETE FROM ticket WHERE id IN (' . $ids . ')');
            }
        }

        $verbinding->exec('DELETE FROM bezoeker WHERE emailadres = ' . $verbinding->quote($eigenTestEmail));
    } catch (Throwable) {
        // Opruimen is een extraatje: een fout hieronder mag de uitkomst niet
        // overschrijven.
    }
});

/**
 * Doet een HTTP-verzoek en geeft status, Location en de body terug.
 *
 * @param  array<string, mixed> $velden
 * @return array{status: int, locatie: string, body: string}
 */
function verzoek(string $methode, string $pad, array $velden = []): array
{
    $opties = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HEADER         => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($methode),
    ];

    if (strtoupper($methode) !== 'GET' && $velden !== []) {
        $opties[CURLOPT_POSTFIELDS] = http_build_query($velden);
        $opties[CURLOPT_HTTPHEADER] = ['Content-Type: application/x-www-form-urlencoded'];
    }

    $curl = curl_init(BASIS_URL . $pad);
    curl_setopt_array($curl, $opties);

    $antwoord = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $koppen = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    curl_close($curl);

    if ($antwoord === false) {
        return ['status' => 0, 'locatie' => '', 'body' => ''];
    }

    $locatie = '';

    foreach (explode("\n", substr($antwoord, 0, $koppen)) as $kop) {
        if (stripos($kop, 'Location:') === 0) {
            $locatie = trim(substr($kop, 9));
        }
    }

    return [
        'status'  => $status,
        'locatie' => $locatie,
        'body'    => substr($antwoord, $koppen),
    ];
}

/**
 * De beschikbaarheid van één tijdslot van een editie.
 */
function beschikbaarheid(int $evenementId, int $prijsId): int
{
    foreach ((new TijdslotModel())->haalOverzicht($evenementId) as $tijdslot) {
        if ($tijdslot->prijsId === $prijsId) {
            return $tijdslot->beschikbareTickets;
        }
    }

    return 0;
}

/**
 * De actuele gegevens van een ticket uit de database.
 *
 * @return array<string, mixed>|false
 */
function ticketRij(int $ticketnummer): array|false
{
    return Database::verbinding()->query(
        'SELECT aantal_tickets, is_actief, prijs_id FROM ticket WHERE id = ' . $ticketnummer
    )->fetch();
}

/** @param bool $voorwaarde */
function controleer(bool $voorwaarde, string $melding): void
{
    global $fouten;

    if ($voorwaarde) {
        echo 'OK   ' . $melding . PHP_EOL;
        return;
    }

    $fouten[] = $melding;
    echo 'FOUT ' . $melding . PHP_EOL;
}

/**
 * Plaatst een bestelling via de bestel- en betaalflow en geeft het
 * ticketnummer terug (0 wanneer het niet lukte).
 *
 * @param array<string, mixed> $velden
 */
function plaatsBestelling(array $velden): int
{
    verzoek('POST', '/tickets/bestellen', $velden);

    $antwoord = verzoek('POST', '/tickets/betalen', [...$velden, 'betaling' => 'geslaagd']);

    if (preg_match('/besteld=1&ticket=(\d+)/', $antwoord['locatie'], $deel) === 1) {
        return (int) $deel[1];
    }

    return 0;
}

// --- Bereid de test voor ---------------------------------------------------

try {
    $verbinding = Database::verbinding();
    $verbinding->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Throwable $fout) {
    echo 'FOUT: geen verbinding met de database: ' . $fout->getMessage() . PHP_EOL;
    exit(1);
}

$testReeks = verzoek('GET', '/tickets');

if ($testReeks['status'] !== 200) {
    echo 'FOUT: de developmentserver draait niet op ' . BASIS_URL
        . PHP_EOL . 'Start hem met: php -S 127.0.0.1:8000 server.php' . PHP_EOL;
    exit(1);
}

/**
 * Zoekt een editie met een tijdslot met genoeg ruimte.
 *
 * @param string $voorwaarde SQL voor de editie (bijv. datum > CURDATE())
 * @return array{evenement: int, prijs: int}|null
 */
function kiesTijdslot(string $voorwaarde): ?array
{
    $verbinding = Database::verbinding();

    $rij = $verbinding->query(
        'SELECT e.id AS evenement, p.id AS prijs
         FROM evenement e
         INNER JOIN prijs p ON p.evenement_id = e.id AND p.is_actief = 1
         WHERE e.is_actief = 1
           AND ' . $voorwaarde . '
           AND e.aantal_tickets_per_tijdslot
               - COALESCE((SELECT SUM(t.aantal_tickets) FROM ticket t
                           WHERE t.prijs_id = p.id AND t.is_actief = 1), 0) >= 10
         ORDER BY e.datum ASC
         LIMIT 1'
    )->fetch();

    return is_array($rij)
        ? ['evenement' => (int) $rij['evenement'], 'prijs' => (int) $rij['prijs']]
        : null;
}

$toekomst = kiesTijdslot('e.datum > CURDATE()');
$verleden = kiesTijdslot('e.datum < CURDATE()');

if ($toekomst === null) {
    echo 'FOUT: er is geen toekomstige editie om voor te bestellen.' . PHP_EOL;
    exit(1);
}

if ($verleden === null) {
    echo 'FOUT: er is geen voorbije editie om de termijn mee te testen.' . PHP_EOL;
    exit(1);
}

$beschikbaarStart = beschikbaarheid($toekomst['evenement'], $toekomst['prijs']);
$beschikbaarStartVerleden = beschikbaarheid($verleden['evenement'], $verleden['prijs']);

// Een bestelling voor een toekomstig event (wijzigen en annuleren mag) en een
// bestelling voor een event dat al begonnen is (de termijn is verstreken).
$ticketnummer = plaatsBestelling([
    'evenement_id' => $toekomst['evenement'],
    'prijs_id'     => $toekomst['prijs'],
    'aantal'       => 2,
    'naam'         => 'Wijzig Test',
    'emailadres'   => $eigenTestEmail,
]);

$aangemaakteTickets[] = $ticketnummer;

$verledenTicket = plaatsBestelling([
    'evenement_id' => $verleden['evenement'],
    'prijs_id'     => $verleden['prijs'],
    'aantal'       => 1,
    'naam'         => 'Wijzig Test',
    'emailadres'   => $eigenTestEmail,
]);

$aangemaakteTickets[] = $verledenTicket;

if ($ticketnummer < 1 || $verledenTicket < 1) {
    echo 'FOUT: de testbestellingen konden niet geplaatst worden.' . PHP_EOL;
    exit(1);
}

// De beschikbaarheid ná het plaatsen: daarbovenop worden de wijzigingen en
// annuleringen hieronder gecontroleerd.
$beschikbaarNaPlaatsing = beschikbaarheid($toekomst['evenement'], $toekomst['prijs']);
$beschikbaarNaPlaatsingVerleden = beschikbaarheid($verleden['evenement'], $verleden['prijs']);

$zoekUrl = '?email=' . rawurlencode($eigenTestEmail) . '&ticket=';

// --- 1. De zoekpagina's ----------------------------------------------------

$antwoord = verzoek('GET', '/tickets/wijzigen');
controleer($antwoord['status'] === 200, 'GET /tickets/wijzigen geeft 200');
controleer(str_contains($antwoord['body'], 'Bestelling wijzigen'), 'het zoekformulier wordt getoond');

$antwoord = verzoek('GET', '/tickets/annuleren');
controleer($antwoord['status'] === 200, 'GET /tickets/annuleren geeft 200');
controleer(str_contains($antwoord['body'], 'Bestelling annuleren'), 'het zoekformulier wordt getoond');

$antwoord = verzoek('GET', '/tickets/wijzigen?email=' . rawurlencode($eigenTestEmail));
controleer($antwoord['status'] === 422, 'alleen een e-mailadres geeft 422');
controleer(
    str_contains($antwoord['body'], 'Vul een geldig ticketnummer in'),
    'de bezoeker wordt gevraagd een ticketnummer in te vullen'
);

// --- 2. Happy flow: de bestelling ophalen -----------------------------------

$antwoord = verzoek('GET', '/tickets/wijzigen' . $zoekUrl . $ticketnummer);
controleer($antwoord['status'] === 200, 'een bestelling ophalen geeft 200');
controleer(str_contains($antwoord['body'], 'Je bestelling'), 'de bestelgegevens worden getoond');
controleer(
    str_contains($antwoord['body'], 'name="aantal"'),
    'het wijzigformulier met het aantal staat op de pagina'
);
controleer(
    preg_match('/name="aantal"[^>]*value="2"/', $antwoord['body']) === 1,
    'het aantal staat voorgevuld met de huidige waarde'
);

// --- 3. Unhappy flow: ongeldige invoer --------------------------------------

$antwoord = verzoek('POST', '/tickets/wijzigen', [
    'emailadres'   => $eigenTestEmail,
    'ticketnummer' => $ticketnummer,
    'aantal'       => 0,
]);
controleer($antwoord['status'] === 422, 'een aantal van nul geeft 422');
controleer(
    str_contains($antwoord['body'], 'Vul een geldig aantal in (minimaal 1).'),
    'de bezoeker ziet een melding over het aantal'
);

$antwoord = verzoek('POST', '/tickets/wijzigen', [
    'emailadres'   => 'geen-email',
    'ticketnummer' => $ticketnummer,
    'aantal'       => 3,
]);
controleer($antwoord['status'] === 422, 'een ongeldig e-mailadres geeft 422');

$antwoord = verzoek('POST', '/tickets/wijzigen', [
    'emailadres'   => 'iemand.anders@voorbeeld.nl',
    'ticketnummer' => $ticketnummer,
    'aantal'       => 3,
]);
controleer($antwoord['status'] === 404, 'een onbekende bestelling geeft 404');
controleer(
    str_contains($antwoord['body'], 'De bestelling is niet gevonden.'),
    'de bezoeker ziet dat de bestelling niet gevonden is'
);

$antwoord = verzoek('POST', '/tickets/wijzigen', [
    'emailadres'   => $eigenTestEmail,
    'ticketnummer' => $ticketnummer,
    'aantal'       => 9999,
]);
controleer($antwoord['status'] === 422, 'meer tickets dan beschikbaar geeft 422');
controleer(
    str_contains($antwoord['body'], 'tickets beschikbaar voor dit tijdslot'),
    'de bezoeker ziet hoeveel tickets er nog beschikbaar zijn'
);

$rij = ticketRij($ticketnummer);
controleer(
    is_array($rij) && (int) $rij['aantal_tickets'] === 2,
    'na een mislukte wijziging blijft het oude aantal staan'
);

// --- 4. Happy flow: het aantal wijzigen -------------------------------------

$antwoord = verzoek('POST', '/tickets/wijzigen', [
    'emailadres'   => $eigenTestEmail,
    'ticketnummer' => $ticketnummer,
    'aantal'       => 3,
]);
controleer($antwoord['status'] === 303, 'een geslaagde wijziging geeft 303 (redirect, PRG)');
controleer(
    str_contains($antwoord['locatie'], 'gewijzigd=1'),
    'de redirect gaat naar de bevestigingspagina'
);

$bevestiging = verzoek('GET', $antwoord['locatie']);
controleer($bevestiging['status'] === 200, 'de bevestigingspagina geeft 200');
controleer(
    str_contains($bevestiging['body'], 'Je bestelling is gewijzigd.'),
    'de bezoeker ziet de melding "Je bestelling is gewijzigd."'
);
controleer(
    str_contains($bevestiging['body'], 'Wijziging verstuurd naar ' . $eigenTestEmail),
    'de wijziging wordt verstuurd naar het e-mailadres van de koper'
);
controleer(
    str_contains($bevestiging['body'], 'bijbetaald'),
    'het prijsverschil dat bijbetaald wordt staat op de bevestiging'
);
controleer(
    str_contains($bevestiging['body'], 'name="aantal"') === false,
    'na een wijziging staat er geen openstaand wijzigformulier meer'
);

$rij = ticketRij($ticketnummer);
controleer(
    is_array($rij) && (int) $rij['aantal_tickets'] === 3,
    'het aantal tickets is in de database aangepast'
);

$beschikbaarNaWijziging = beschikbaarheid($toekomst['evenement'], $toekomst['prijs']);
controleer(
    $beschikbaarNaWijziging === $beschikbaarNaPlaatsing - 1,
    'de beschikbaarheid gaat met de extra tickets omlaag'
);

// --- 5. Unhappy flow: de wijzigingstermijn is verstreken --------------------

$antwoord = verzoek('GET', '/tickets/wijzigen' . $zoekUrl . $verledenTicket);
controleer($antwoord['status'] === 422, 'wijzigen na de termijn geeft 422');
controleer(
    str_contains($antwoord['body'], 'Je bestelling kan niet meer worden gewijzigd.'),
    'de bezoeker ziet "Je bestelling kan niet meer worden gewijzigd."'
);
controleer(
    str_contains($antwoord['body'], 'name="aantal"') === false,
    'er is geen wijzigformulier meer voor een verstreken termijn'
);

$antwoord = verzoek('POST', '/tickets/wijzigen', [
    'emailadres'   => $eigenTestEmail,
    'ticketnummer' => $verledenTicket,
    'aantal'       => 5,
]);
controleer($antwoord['status'] === 422, 'wijzigen na de termijn geeft 422 bij het opslaan');
controleer(
    str_contains($antwoord['body'], 'Je bestelling kan niet meer worden gewijzigd.'),
    'de bezoeker ziet ook bij het opslaan de termijnmelding'
);

$rij = ticketRij($verledenTicket);
controleer(
    is_array($rij) && (int) $rij['aantal_tickets'] === 1 && (int) $rij['is_actief'] === 1,
    'bij een verstreken termijn blijft de bestelling ongewijzigd'
);

// --- 6. Happy flow: de bestelling annuleren ---------------------------------

$antwoord = verzoek('GET', '/tickets/annuleren' . $zoekUrl . $ticketnummer);
controleer($antwoord['status'] === 200, 'de annuleerpagina toont de bestelling (200)');
controleer(
    str_contains($antwoord['body'], 'Ja, annuleer mijn bestelling'),
    'er is een bevestigingsknop voor de annulering'
);

$antwoord = verzoek('POST', '/tickets/annuleren', [
    'emailadres'   => $eigenTestEmail,
    'ticketnummer' => $ticketnummer,
]);
controleer($antwoord['status'] === 303, 'een geslaagde annulering geeft 303 (redirect, PRG)');
controleer(
    str_contains($antwoord['locatie'], 'geannuleerd=1'),
    'de redirect gaat naar de annuleerbevestiging'
);

$bevestiging = verzoek('GET', $antwoord['locatie']);
controleer($bevestiging['status'] === 200, 'de annuleerbevestiging geeft 200');
controleer(
    str_contains($bevestiging['body'], 'De annulering is gelukt.'),
    'de bezoeker ziet de melding "De annulering is gelukt."'
);
controleer(
    str_contains($bevestiging['body'], 'Annulering verstuurd naar ' . $eigenTestEmail),
    'de annulering wordt verstuurd naar het e-mailadres van de koper'
);
controleer(
    str_contains($bevestiging['body'], 'Terugbetaald'),
    'het terugbetaalde bedrag staat op de bevestiging'
);
controleer(
    str_contains($bevestiging['body'], 'Geannuleerd'),
    'de status van de bestelling is nu "Geannuleerd"'
);

$rij = ticketRij($ticketnummer);
controleer(
    is_array($rij) && (int) $rij['is_actief'] === 0,
    'de tickets zijn ongemaakt (is_actief = 0)'
);

$beschikbaarNaAnnulering = beschikbaarheid($toekomst['evenement'], $toekomst['prijs']);
controleer(
    $beschikbaarNaAnnulering === $beschikbaarStart,
    'na de annulering is de beschikbaarheid weer als het begin'
);

// --- 7. Unhappy flow: dubbel annuleren --------------------------------------

$antwoord = verzoek('POST', '/tickets/annuleren', [
    'emailadres'   => $eigenTestEmail,
    'ticketnummer' => $ticketnummer,
]);
controleer($antwoord['status'] === 422, 'een tweede annulering geeft 422');
controleer(
    str_contains($antwoord['body'], 'Deze bestelling is al geannuleerd.'),
    'de bezoeker ziet dat de bestelling al geannuleerd is'
);

// --- 8. Unhappy flow: de annuleringstermijn is verstreken -------------------

$antwoord = verzoek('GET', '/tickets/annuleren' . $zoekUrl . $verledenTicket);
controleer($antwoord['status'] === 422, 'annuleren na de termijn geeft 422');
controleer(
    str_contains($antwoord['body'], 'Je bestelling kan niet meer worden geannuleerd.'),
    'de bezoeker ziet "Je bestelling kan niet meer worden geannuleerd."'
);
controleer(
    str_contains($antwoord['body'], 'Ja, annuleer mijn bestelling') === false,
    'er is geen bevestigingsknop meer voor een verstreken termijn'
);

$antwoord = verzoek('POST', '/tickets/annuleren', [
    'emailadres'   => $eigenTestEmail,
    'ticketnummer' => $verledenTicket,
]);
controleer($antwoord['status'] === 422, 'annuleren na de termijn geeft 422 bij het opslaan');

$rij = ticketRij($verledenTicket);
controleer(
    is_array($rij) && (int) $rij['is_actief'] === 1,
    'bij een verstreken termijn blijven de tickets geldig'
);

$beschikbaarVerleden = beschikbaarheid($verleden['evenement'], $verleden['prijs']);
controleer(
    $beschikbaarVerleden === $beschikbaarNaPlaatsingVerleden,
    'de bestelling van het voorbije event is niet veranderd'
);

// --- 9. Unhappy flow: de database is niet bereikbaar ------------------------

$origineel = file_get_contents(CONFIGURATIE);

if ($origineel === false) {
    $fouten[] = 'de configuratie kon niet gelezen worden voor de foutdemo';
    echo 'FOUT: de configuratie kon niet gelezen worden voor de foutdemo.' . PHP_EOL;
} else {
    // De poort even verleggen, zodat de applicatie de database niet ziet.
    $kapotteConfiguratie = preg_replace("/('port'\s*=>\s*)3306/", '${1}3307', $origineel, 1);

    if ($kapotteConfiguratie === null || $kapotteConfiguratie === $origineel) {
        $fouten[] = 'de databaspoort kon niet voor de foutdemo verlegd worden';
    } else {
        file_put_contents(CONFIGURATIE, $kapotteConfiguratie);

        // De PHP-server cacheert PHP-bestanden maximaal 2 seconden
        // (opcache.revalidate_freq); even wachten zodat de kapotte
        // configuratie ook echt gelezen wordt.
        sleep(3);

        try {
            $antwoord = verzoek('POST', '/tickets/wijzigen', [
                'emailadres'   => $eigenTestEmail,
                'ticketnummer' => $ticketnummer,
                'aantal'       => 4,
            ]);
            controleer($antwoord['status'] === 503, 'een technische fout bij wijzigen geeft HTTP 503');
            controleer(
                str_contains($antwoord['body'], 'Je bestelling kon niet worden gewijzigd.'),
                'de bezoeker ziet dat de wijziging niet opgeslagen is'
            );

            $antwoord = verzoek('POST', '/tickets/annuleren', [
                'emailadres'   => $eigenTestEmail,
                'ticketnummer' => $verledenTicket,
            ]);
            controleer($antwoord['status'] === 503, 'een technische fout bij annuleren geeft HTTP 503');
            controleer(
                str_contains($antwoord['body'], 'Je bestelling kon niet worden geannuleerd.'),
                'de bezoeker ziet dat de annulering niet opgeslagen is'
            );
        } finally {
            file_put_contents(CONFIGURATIE, $origineel);
            sleep(3);
        }

        $rij = ticketRij($ticketnummer);
        $configuratieHersteld = preg_match(
            "/'port'\s*=>\s*3306/",
            (string) file_get_contents(CONFIGURATIE)
        ) === 1;

        controleer(
            is_array($rij) && (int) $rij['aantal_tickets'] === 3 && (int) $rij['is_actief'] === 0,
            'bij een technische fout verandert er niets aan de bestelling'
        );
        controleer($configuratieHersteld, 'de configuratie is na de foutdemo hersteld');
    }
}

// --- Opruimen ---------------------------------------------------------------

if ($aangemaakteTickets !== []) {
    $ids = implode(', ', array_filter($aangemaakteTickets));
    $verbinding->exec('DELETE FROM ticket WHERE id IN (' . $ids . ')');
}

$verbinding->exec('DELETE FROM bezoeker WHERE emailadres = ' . $verbinding->quote($eigenTestEmail));

$beschikbaarEinde = beschikbaarheid($toekomst['evenement'], $toekomst['prijs']);
$beschikbaarEindeVerleden = beschikbaarheid($verleden['evenement'], $verleden['prijs']);
$bezoekersEinde = (int) $verbinding->query(
    'SELECT COUNT(*) FROM bezoeker WHERE emailadres = ' . $verbinding->quote($eigenTestEmail)
)->fetchColumn();

controleer($beschikbaarEinde === $beschikbaarStart, 'de testtickets zijn weer opgeruimd');
controleer($beschikbaarEindeVerleden === $beschikbaarStartVerleden, 'de testbestelling van het voorbije event is weer opgeruimd');
controleer($bezoekersEinde === 0, 'de testbezoeker is weer opgeruimd');

// --- Uitkomst ---------------------------------------------------------------

echo PHP_EOL;

if ($fouten !== []) {
    echo count($fouten) . ' controle(s) mislukt:' . PHP_EOL;

    foreach ($fouten as $fout) {
        echo ' - ' . $fout . PHP_EOL;
    }

    exit(1);
}

echo 'Alle controles van het wijzigen en annuleren zijn geslaagd.' . PHP_EOL;
exit(0);
