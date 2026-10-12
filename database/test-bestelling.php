<?php

declare(strict_types=1);

/**
 * Controleert de bestel- en betaalflow van de bezoeker (user story "Als
 * bezoeker wil ik een ticket voor een evenement kunnen kopen"):
 *
 *  - happy  -> bestellen (naam, e-mailadres, aantal) -> betaalscherm ->
 *              "Betaling afronden" -> 303 met de melding "Je bestelling is
 *              gelukt.", een bevestiging en een ticket dat op naam van de
 *              bezoeker staat; de beschikbaarheid gaat omlaag.
 *  - unhappy -> ongeldige invoer (422), een mislukte betaling (303, niets
 *              geplaatst), meer tickets dan beschikbaar (422) en een
 *              technische fout, bijv. de database niet bereikbaar (503, geen
 *              ticket en geen bezoeker aangemaakt).
 *
 * Het script heeft de developmentserver nodig:
 *     php -S 127.0.0.1:8000 -t public
 *
 * Alles wat deze test aanmaakt wordt na afloop weer opgeruimd.
 *
 * Gebruik: php database/test-bestelling.php
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;
use App\Models\EvenementModel;
use App\Models\TijdslotModel;

const BASIS_URL = 'http://127.0.0.1:8000';
const CONFIGURATIE = __DIR__ . '/../config/config.php';

/** @var list<string> $fouten */
$fouten = [];

/** @var list<int> $aangemaakteTickets */
$aangemaakteTickets = [];

$eigenTestEmail = 'besteltest-' . bin2hex(random_bytes(4)) . '@voorbeeld.nl';

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
        . PHP_EOL . 'Start hem met: php -S 127.0.0.1:8000 -t public' . PHP_EOL;
    exit(1);
}

$evenement = (new EvenementModel())->haalOverzicht()[0] ?? null;

if ($evenement === null) {
    echo 'FOUT: er is geen actieve editie om voor te bestellen.' . PHP_EOL;
    exit(1);
}

$tijdslot = null;

foreach ((new TijdslotModel())->haalOverzicht($evenement->id) as $kandidaat) {
    if ($kandidaat->beschikbareTickets >= 10) {
        $tijdslot = $kandidaat;
        break;
    }
}

if ($tijdslot === null) {
    echo 'FOUT: er is geen tijdslot met genoeg beschikbare tickets.' . PHP_EOL;
    exit(1);
}

$velden = [
    'evenement_id' => $evenement->id,
    'prijs_id'     => $tijdslot->prijsId,
    'aantal'       => 2,
    'naam'         => 'Bestel Test',
    'emailadres'   => $eigenTestEmail,
];

$ticketsStart = (int) $verbinding->query('SELECT COUNT(*) FROM ticket')->fetchColumn();
$bezoekersStart = (int) $verbinding->query('SELECT COUNT(*) FROM bezoeker')->fetchColumn();
$beschikbaarStart = beschikbaarheid($evenement->id, $tijdslot->prijsId);

$bestelPad = '/tickets/bestellen?event=' . $evenement->id . '&tijdslot=' . $tijdslot->prijsId;

// --- 1. Het bestelformulier ------------------------------------------------

$antwoord = verzoek('GET', $bestelPad);
controleer($antwoord['status'] === 200, 'GET /tickets/bestellen geeft 200');
controleer(str_contains($antwoord['body'], 'Tickets bestellen'), 'het bestelformulier wordt getoond');

// --- 2. Ongeldige invoer ---------------------------------------------------

$antwoord = verzoek('POST', '/tickets/bestellen', [...$velden, 'emailadres' => 'geen-email']);
controleer($antwoord['status'] === 422, 'een ongeldig e-mailadres geeft 422');
controleer(
    str_contains($antwoord['body'], 'Vul een geldig e-mailadres in'),
    'de bezoeker ziet een melding over het e-mailadres'
);

$antwoord = verzoek('POST', '/tickets/bestellen', [...$velden, 'naam' => '']);
controleer($antwoord['status'] === 422, 'een lege naam geeft 422');

$antwoord = verzoek('POST', '/tickets/bestellen', [...$velden, 'aantal' => 9999]);
controleer($antwoord['status'] === 422, 'meer tickets dan beschikbaar geeft 422');

$bezoekersNaValidatie = (int) $verbinding->query('SELECT COUNT(*) FROM bezoeker')->fetchColumn();
controleer(
    $bezoekersNaValidatie === $bezoekersStart,
    'bij een validatiefout wordt er geen bezoeker aangemaakt'
);

// --- 3. Het betaalscherm ---------------------------------------------------

$antwoord = verzoek('POST', '/tickets/bestellen', $velden);
controleer($antwoord['status'] === 200, 'een geldige bestelling geeft het betaalscherm (200)');
controleer(str_contains($antwoord['body'], 'Betaling afronden'), 'het betaalscherm heeft de knop "Betaling afronden"');
controleer(str_contains($antwoord['body'], 'Betaling mislukt'), 'het betaalscherm heeft de knop "Betaling mislukt"');

$ticketsNaBetaalscherm = (int) $verbinding->query('SELECT COUNT(*) FROM ticket')->fetchColumn();
controleer(
    $ticketsNaBetaalscherm === $ticketsStart,
    'op het betaalscherm is er nog geen ticket geplaatst'
);

// --- 4. Unhappy: de betaling mislukt --------------------------------------

$antwoord = verzoek('POST', '/tickets/betalen', [...$velden, 'betaling' => 'mislukt']);
controleer($antwoord['status'] === 303, 'een mislukte betaling geeft 303 (geen bestelling opnieuw opslaan)');
controleer(
    str_contains($antwoord['locatie'], 'betaling=mislukt'),
    'de bezoeker gaat terug naar de ticketpagina met een melding'
);

$ticketsNaMislukteBetaling = (int) $verbinding->query('SELECT COUNT(*) FROM ticket')->fetchColumn();
$bezoekersNaMislukteBetaling = (int) $verbinding->query('SELECT COUNT(*) FROM bezoeker')->fetchColumn();
controleer(
    $ticketsNaMislukteBetaling === $ticketsStart && $bezoekersNaMislukteBetaling === $bezoekersStart,
    'bij een mislukte betaling wordt er geen ticket en geen bezoeker aangemaakt'
);

// --- 5. Happy: de betaling slagen -----------------------------------------

$antwoord = verzoek('POST', '/tickets/betalen', [...$velden, 'betaling' => 'geslaagd']);
controleer($antwoord['status'] === 303, 'een geslaagde betaling geeft 303 (redirect, PRG)');

$ticketnummer = 0;

if (preg_match('/besteld=1&ticket=(\d+)/', $antwoord['locatie'], $deel) === 1) {
    $ticketnummer = (int) $deel[1];
}

controleer($ticketnummer > 0, 'de redirect bevat het nieuwe ticketnummer');
$aangemaakteTickets[] = $ticketnummer;

$bevestiging = verzoek('GET', $antwoord['locatie']);
controleer($bevestiging['status'] === 200, 'de ticketpagina geeft 200 na de bestelling');
controleer(
    str_contains($bevestiging['body'], 'Je bestelling is gelukt.'),
    'de bezoeker ziet de melding "Je bestelling is gelukt."'
);
controleer(
    str_contains($bevestiging['body'], 'Bevestiging verstuurd naar ' . $eigenTestEmail),
    'de bevestiging met de tickets wordt getoond op het e-mailadres van de koper'
);

$rij = $verbinding->query('SELECT bezoeker_id FROM ticket WHERE id = ' . $ticketnummer)->fetch();
$bezoekerId = (int) ($rij['bezoeker_id'] ?? 0);
controleer($bezoekerId > 0, 'het ticket hangt aan een bezoeker (bezoeker_id is ingevuld)');

$rij = $verbinding->query('SELECT naam FROM bezoeker WHERE id = ' . $bezoekerId)->fetch();
controleer(($rij['naam'] ?? '') === 'Bestel Test', 'de bezoeker is met de ingevulde naam aangemaakt');

$beschikbaarNu = beschikbaarheid($evenement->id, $tijdslot->prijsId);
controleer(
    $beschikbaarNu === $beschikbaarStart - $velden['aantal'],
    'de beschikbaarheid op /tickets gaat met het aantal tickets omlaag'
);

// --- 6. Dezelfde koper bestelt opnieuw -------------------------------------

$antwoord = verzoek('POST', '/tickets/betalen', [
    ...$velden,
    'aantal'   => 1,
    'naam'     => 'Bestel Test Jr',
    'betaling' => 'geslaagd',
]);

$tweedeTicket = 0;

if (preg_match('/besteld=1&ticket=(\d+)/', $antwoord['locatie'], $deel) === 1) {
    $tweedeTicket = (int) $deel[1];
    $aangemaakteTickets[] = $tweedeTicket;
}

$rij = $tweedeTicket > 0
    ? $verbinding->query('SELECT bezoeker_id FROM ticket WHERE id = ' . $tweedeTicket)->fetch()
    : false;
$bezoekersTweedeBestelling = (int) $verbinding->query('SELECT COUNT(*) FROM bezoeker')->fetchColumn();

controleer($antwoord['status'] === 303, 'een tweede bestelling van dezelfde koper slaagt');
controleer(
    is_array($rij) ? (int) ($rij['bezoeker_id'] ?? 0) === $bezoekerId : false,
    'hetzelfde e-mailadres levert geen tweede bezoeker op'
);
controleer(
    $bezoekersTweedeBestelling === $bezoekersStart + 1,
    'er komt precies één bezoeker bij in de database'
);

// --- 7. Unhappy: de database is niet bereikbaar ---------------------------

$origineel = file_get_contents(CONFIGURATIE);

if ($origineel === false) {
    $fouten[] = 'de configuratie kon niet gelezen worden voor de foutdemo';
    echo 'FOUT: de configuratie kon niet gelezen worden voor de foutdemo.' . PHP_EOL;
} else {
    // De poort even verleggen, zodat de applicatie de database niet ziet.
    // Let op: '${1}3307' (en niet '$1' . '3307'), anders leest preg_replace
    // het geheel als één onbestaande groepsverwijzing.
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
            $antwoord = verzoek('POST', '/tickets/betalen', [...$velden, 'betaling' => 'geslaagd']);
            controleer($antwoord['status'] === 503, 'een technische fout geeft HTTP 503');
            controleer(
                str_contains($antwoord['body'], 'Je bestelling is niet geplaatst. Probeer het later opnieuw.'),
                'de bezoeker ziet dat de bestelling niet geplaatst is'
            );
        } finally {
            file_put_contents(CONFIGURATIE, $origineel);
            sleep(3);
        }

        $ticketsNaFout = (int) $verbinding->query('SELECT COUNT(*) FROM ticket')->fetchColumn();
        $bezoekersNaFout = (int) $verbinding->query('SELECT COUNT(*) FROM bezoeker')->fetchColumn();
        $configuratieHersteld = preg_match(
            "/'port'\s*=>\s*3306/",
            (string) file_get_contents(CONFIGURATIE)
        ) === 1;

        controleer($ticketsNaFout === $ticketsStart + 2, 'bij een technische fout komt er geen ticket bij');
        controleer($bezoekersNaFout === $bezoekersStart + 1, 'bij een technische fout komt er geen bezoeker bij');
        controleer($configuratieHersteld, 'de configuratie is na de foutdemo hersteld');
    }
}

// --- Opruimen ---------------------------------------------------------------

if ($aangemaakteTickets !== []) {
    $ids = implode(', ', array_filter($aangemaakteTickets));
    $verbinding->exec('DELETE FROM ticket WHERE id IN (' . $ids . ')');
}

$verbinding->exec('DELETE FROM bezoeker WHERE emailadres = ' . $verbinding->quote($eigenTestEmail));

$ticketsEinde = (int) $verbinding->query('SELECT COUNT(*) FROM ticket')->fetchColumn();
$bezoekersEinde = (int) $verbinding->query('SELECT COUNT(*) FROM bezoeker')->fetchColumn();

controleer($ticketsEinde === $ticketsStart, 'de testtickets zijn weer opgeruimd');
controleer($bezoekersEinde === $bezoekersStart, 'de testbezoeker is weer opgeruimd');

// --- Uitkomst ---------------------------------------------------------------

echo PHP_EOL;

if ($fouten !== []) {
    echo count($fouten) . ' controle(s) mislukt:' . PHP_EOL;

    foreach ($fouten as $fout) {
        echo ' - ' . $fout . PHP_EOL;
    }

    exit(1);
}

echo 'Alle controles van de bestel- en betaalflow zijn geslaagd.' . PHP_EOL;
exit(0);
