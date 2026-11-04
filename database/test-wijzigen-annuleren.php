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
 *              geforceerde databasefout (503, dezelfde trigger als
 *              `php database/demo_unhappy.php uit bestelling`).
 *
 * Het script heeft de developmentserver nodig:
 *     php -S 127.0.0.1:8000 server.php
 *
 * De ticketpagina's zijn alleen voor ingelogde gebruikers bereikbaar: de test
 * logt zelf in als beheerder (noah) en controleert dat een bezoeker zonder
 * login wordt doorgestuurd naar /inloggen.
 *
 * Alles wat deze test aanmaakt wordt na afloop weer opgeruimd.
 *
 * Gebruik: php database/test-wijzigen-annuleren.php
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;
use App\Models\TijdslotModel;

const BASIS_URL = 'http://127.0.0.1:8000';

/**
 * Sessiebestand waarin de test de inlogcookie bewaart: de ticketpagina's zijn
 * voortaan alleen voor ingelogde gebruikers bereikbaar.
 */
define('COOKIE_BESTAND', sys_get_temp_dir() . '/sneakerness-test-cookies.txt');

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

    // Ook de inlogcookie van de test ruimen we op.
    @unlink(COOKIE_BESTAND);
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
        CURLOPT_COOKIEJAR      => COOKIE_BESTAND,
        CURLOPT_COOKIEFILE     => COOKIE_BESTAND,
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
 * Logt in als beheerder, zodat de ticketpagina's bereikbaar zijn.
 *
 * Stopt het script wanneer inloggen niet lukt: zonder sessie kan er geen enkele
 * ticketcontrole uitgevoerd worden.
 */
function logIn(): void
{
    $antwoord = verzoek('POST', '/inloggen', [
        'gebruikersnaam' => 'noah',
        'wachtwoord'     => 'Sneakerness2026!',
    ]);

    if ($antwoord['status'] !== 302) {
        echo 'FOUT: inloggen als noah lukte niet (HTTP ' . $antwoord['status'] . '). '
            . 'Start de server met: php -S 127.0.0.1:8000 server.php' . PHP_EOL;

        exit(1);
    }
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

$testReeks = verzoek('GET', '/');

if ($testReeks['status'] !== 200) {
    echo 'FOUT: de developmentserver draait niet op ' . BASIS_URL
        . PHP_EOL . 'Start hem met: php -S 127.0.0.1:8000 server.php' . PHP_EOL;
    exit(1);
}

// Zonder login zijn de ticketpagina's niet bereikbaar: /tickets en
// /tickets/wijzigen stuurt door naar /inloggen. Daarna logt de test in, zodat
// de wijzig- en annuleerflow verder kan.
$afgemeld = verzoek('GET', '/tickets');
controleer($afgemeld['status'] === 302, 'zonder login geeft /tickets een doorverwijzing');
controleer(
    str_contains($afgemeld['locatie'], '/inloggen'),
    'de doorverwijzing gaat naar de inlogpagina'
);

logIn();

$ingelogd = verzoek('GET', '/tickets');
controleer($ingelogd['status'] === 200, 'na het inloggen is /tickets bereikbaar');

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

$tarief = (float) $verbinding->query(
    'SELECT tarief FROM prijs WHERE id = ' . $toekomst['prijs']
)->fetchColumn();

$zoekUrl = '?email=' . rawurlencode($eigenTestEmail) . '&ticket=';

// --- 1a. De bevestiging van de bestelling toont de verstuurde mail ---------

$bevestiging = verzoek('GET', '/tickets?besteld=1&ticket=' . $ticketnummer);
controleer($bevestiging['status'] === 200, 'de bevestigingspagina van de bestelling geeft 200');
controleer(
    str_contains($bevestiging['body'], 'Verstuurde mail'),
    'de bevestiging toont de mail die verstuurd is'
);
controleer(
    str_contains($bevestiging['body'], 'Hieronder vind je je tickets.'),
    'de mail bevat de bestelgegevens'
);

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
controleer(
    str_contains($antwoord['body'], 'Bestellingen op dit e-mailadres') === false,
    'een onbekend e-mailadres levert geen lijst met bestellingen op'
);

// Een bekend e-mailadres met een verkeerd ticketnummer: de bezoeker krijgt
// de bestellingen van dat adres te zien, zodat hij het juiste nummer kan
// kiezen in plaats van te moeten gokken.
$antwoord = verzoek('GET', '/tickets/wijzigen?email=' . rawurlencode($eigenTestEmail) . '&ticket=999999');
controleer($antwoord['status'] === 404, 'een bekend e-mailadres met een verkeerd nummer geeft 404');
controleer(
    str_contains($antwoord['body'], 'Op dit e-mailadres staan de volgende bestellingen'),
    'de bezoeker ziet dat er wél bestellingen op dat adres staan'
);
controleer(
    str_contains($antwoord['body'], 'Ticket #' . $ticketnummer),
    'de lijst toont het juiste ticketnummer'
);
controleer(
    str_contains($antwoord['body'], 'ticket=' . $ticketnummer . '"'),
    'elke regel in de lijst opent de bestelling met één klik'
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
    str_contains($bevestiging['body'], 'Verstuurde mail'),
    'de bevestiging toont de wijzigingsmail'
);
controleer(
    str_contains($bevestiging['body'], 'is gewijzigd: van 2 naar 3 tickets'),
    'de mail beschrijft van welk naar welk aantal gewijzigd is'
);
controleer(
    str_contains($bevestiging['body'], 'Nieuw totaal: ' . euro($tarief * 3)),
    'de mail noemt het nieuwe totaal'
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
controleer(
    str_contains($bevestiging['body'], 'Verstuurde mail'),
    'de bevestiging toont de annuleringsmail'
);
controleer(
    str_contains($bevestiging['body'], 'Je annulering is gelukt'),
    'de mail heeft het onderwerp "Je annulering is gelukt"'
);
controleer(
    str_contains($bevestiging['body'], 'De 3 tickets zijn ongeldig.'),
    'de mail meldt dat de tickets ongeldig zijn'
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

// --- 9. Unhappy flow: de database geeft een fout ----------------------------
//
// Dezelfde trigger als `php database/demo_unhappy.php uit bestelling`: een
// UPDATE op de tabel ticket faalt, waardoor sp_bestelling_wijzigen en
// sp_bestelling_annuleren teruggedraaid worden en de controller een503 toont.
// De site zelf blijft tijdens deze demo gewoon werken (in tegenstelling tot
// de databaspoort even verleggen), dus een bezoeker die tussendoor de pagina
// opent kan gewoon doorgaan.

// Een verse bestelling voor het demo-ticket: die is nog geldig, dus wijzigen
// en annuleren komen tot de UPDATE en lopen daar op de fout.
$foutTicket = plaatsBestelling([
    'evenement_id' => $toekomst['evenement'],
    'prijs_id'     => $toekomst['prijs'],
    'aantal'       => 1,
    'naam'         => 'Wijzig Test',
    'emailadres'   => $eigenTestEmail,
]);

$aangemaakteTickets[] = $foutTicket;

if ($foutTicket < 1) {
    $fouten[] = 'de testbestelling voor de foutdemo kon niet geplaatst worden';
    echo 'FOUT: de testbestelling voor de foutdemo kon niet geplaatst worden.' . PHP_EOL;
} else {
    try {
        $verbinding->exec(
            "CREATE TRIGGER trg_demo_fout_bestelling BEFORE UPDATE ON ticket "
            . "FOR EACH ROW SIGNAL SQLSTATE '45000' "
            . "SET MESSAGE_TEXT = 'Acceptatietest: geforceerde databasefout'"
        );

        $antwoord = verzoek('POST', '/tickets/wijzigen', [
            'emailadres'   => $eigenTestEmail,
            'ticketnummer' => $foutTicket,
            'aantal'       => 4,
        ]);
        controleer($antwoord['status'] === 503, 'een technische fout bij wijzigen geeft HTTP 503');
        controleer(
            str_contains($antwoord['body'], 'Je bestelling kon niet worden gewijzigd.'),
            'de bezoeker ziet dat de wijziging niet opgeslagen is'
        );

        $antwoord = verzoek('POST', '/tickets/annuleren', [
            'emailadres'   => $eigenTestEmail,
            'ticketnummer' => $foutTicket,
        ]);
        controleer($antwoord['status'] === 503, 'een technische fout bij annuleren geeft HTTP 503');
        controleer(
            str_contains($antwoord['body'], 'Je bestelling kon niet worden geannuleerd.'),
            'de bezoeker ziet dat de annulering niet opgeslagen is'
        );
    } finally {
        // De trigger verdwijnt altijd, anders blijft de site in de foutmodus.
        $verbinding->exec('DROP TRIGGER IF EXISTS trg_demo_fout_bestelling');
    }

    $trigger = (int) $verbinding->query(
        "SELECT COUNT(*) FROM information_schema.triggers
         WHERE trigger_schema = DATABASE() AND trigger_name = 'trg_demo_fout_bestelling'"
    )->fetchColumn();

    controleer($trigger === 0, 'de foutdemo-trigger is weer verwijderd');

    $rij = ticketRij($foutTicket);
    controleer(
        is_array($rij) && (int) $rij['aantal_tickets'] === 1 && (int) $rij['is_actief'] === 1,
        'bij een technische fout verandert er niets aan de bestelling'
    );
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
