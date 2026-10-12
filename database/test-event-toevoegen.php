<?php

declare(strict_types=1);

/**
 * Controleert de drie scenario's van "Event toevoegen" (/beheer/events/toevoegen).
 *
 * Happy flow:
 *   verplichte gegevens correct invullen → 302 naar /events?toegevoegd={id},
 *   melding "Het event is succesvol toegevoegd.", het event staat in het
 *   overzicht én in de database.
 *
 * Unhappy flow (verplicht):
 *   lege velden → 422, "Vul de gemarkeerde verplichte velden in.", niets opgeslagen.
 *
 * Unhappy flow (ongeldig):
 *   ongeldige datum → 422, duidelijke veldfout, niets opgeslagen.
 *
 * Gebruik: php database/test-event-toevoegen.php [baseUrl]
 *
 * De server moet draaien; inloggen gebeurt als noah / Sneakerness2026!.
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;

/** @var list<string> $fouten */
$fouten = [];

/** @var array<string, string> $cookies */
$cookies = [];

/**
 * @param  array<string, string> $velden
 * @return array{status: int, headers: list<string>, body: string}|null
 */
function verzoek(string $url, array $velden = []): ?array
{
    global $cookies;

    $isPost = $velden !== [];
    $headers = ['Content-Type: application/x-www-form-urlencoded'];

    if ($cookies !== []) {
        $paren = [];
        foreach ($cookies as $naam => $waarde) {
            $paren[] = $naam . '=' . $waarde;
        }
        $headers[] = 'Cookie: ' . implode('; ', $paren);
    }

    $opties = [
        'http' => [
            'method'          => $isPost ? 'POST' : 'GET',
            'header'          => implode("\r\n", $headers),
            'content'         => http_build_query($velden),
            'ignore_errors'   => true,
            'follow_location' => 0,
            'timeout'         => 15,
        ],
    ];

    $context = stream_context_create($opties);
    $body = @file_get_contents($url, false, $context);
    $ontvangenHeaders = function_exists('http_get_last_response_headers')
        ? (http_get_last_response_headers() ?? [])
        : ($GLOBALS['http_response_header'] ?? []);

    if ($body === false || $ontvangenHeaders === []) {
        return null;
    }

    foreach ($ontvangenHeaders as $regel) {
        if (stripos($regel, 'Set-Cookie:') === 0) {
            $cookie = trim(substr($regel, strlen('Set-Cookie:')));
            $paar = explode(';', $cookie, 2)[0];
            [$naam, $waarde] = array_pad(explode('=', $paar, 2), 2, '');
            if ($naam !== '') {
                $cookies[$naam] = $waarde;
            }
        }
    }

    preg_match('#HTTP/\S+\s+(\d{3})#', $ontvangenHeaders[0], $treffer);

    return [
        'status'  => (int) ($treffer[1] ?? 0),
        'headers' => $ontvangenHeaders,
        'body'    => $body,
    ];
}

/** @param list<string> $headers */
function headerWaarde(array $headers, string $naam): string
{
    foreach ($headers as $regel) {
        if (stripos($regel, $naam . ':') === 0) {
            return trim(substr($regel, strlen($naam) + 1));
        }
    }

    return '';
}

function controle(string $omschrijving, bool $ok): void
{
    echo ($ok ? '  OK   ' : '  FOUT ') . $omschrijving . PHP_EOL;

    if (!$ok) {
        $GLOBALS['fouten'][] = $omschrijving;
    }
}

$baseUrl = $argv[1] ?? '';

if ($baseUrl === '') {
    foreach (['http://127.0.0.1:8090', 'http://127.0.0.1:8080', 'http://127.0.0.1:8000'] as $kandidaat) {
        if (verzoek($kandidaat . '/') !== null) {
            $baseUrl = $kandidaat;
            break;
        }
    }
}

if ($baseUrl === '') {
    echo 'FOUT: de server is niet bereikbaar op poort 8090, 8080 of 8000.' . PHP_EOL;
    echo 'Start de server eerst met: php -S 127.0.0.1:8080 -t public server.php' . PHP_EOL;

    exit(1);
}

echo 'Server              : ' . $baseUrl . PHP_EOL;

$inloggen = verzoek($baseUrl . '/inloggen', [
    'gebruikersnaam' => 'noah',
    'wachtwoord'     => 'Sneakerness2026!',
]);

controle('Inloggen als beheerder geeft een doorverwijzing', $inloggen !== null && $inloggen['status'] === 302);

$suffix = str_replace('.', '', uniqid('', true));
$naam = 'Acceptatietest Event ' . $suffix;
$datum = '2027-06-12';
$locatie = 'Testlocatie, Rotterdam';

echo 'Nieuw event         : ' . $naam . PHP_EOL . PHP_EOL;

echo 'HAPPY FLOW' . PHP_EOL;

$formulier = verzoek($baseUrl . '/beheer/events/toevoegen');
controle('Het formulier laadt (HTTP 200)', $formulier !== null && $formulier['status'] === 200);

if ($formulier !== null) {
    controle(
        'Het formulier bevat naam, datum, locatie, tickets en stands',
        str_contains($formulier['body'], 'name="naam"')
            && str_contains($formulier['body'], 'name="datum"')
            && str_contains($formulier['body'], 'name="locatie"')
            && str_contains($formulier['body'], 'name="aantal_tickets_per_tijdslot"')
            && str_contains($formulier['body'], 'name="beschikbare_stands"')
    );
    controle('Er is een knop "Opslaan"', str_contains($formulier['body'], '>Opslaan<'));
}

$opslaan = verzoek($baseUrl . '/beheer/events/toevoegen', [
    'naam'                        => $naam,
    'datum'                       => $datum,
    'locatie'                     => $locatie,
    'aantal_tickets_per_tijdslot' => '200',
    'beschikbare_stands'          => '18',
    'opmerking'                   => 'Aangemaakt door de acceptatietest',
]);

controle('Opslaan geeft een doorverwijzing (302)', $opslaan !== null && $opslaan['status'] === 302);

$locatieHeader = $opslaan !== null ? headerWaarde($opslaan['headers'], 'Location') : '';
controle('De doorverwijzing gaat naar het eventoverzicht', str_contains($locatieHeader, '/events') && str_contains($locatieHeader, 'toegevoegd='));

$evenementId = 0;

if (preg_match('/toegevoegd=(\d+)/', $locatieHeader, $treffer) === 1) {
    $evenementId = (int) $treffer[1];
}

controle('Het event heeft een id gekregen (' . ($evenementId ?: 'geen') . ')', $evenementId > 0);

$overzicht = verzoek($baseUrl . '/events?toegevoegd=' . ($evenementId ?: 0));
controle(
    'De melding "Het event is succesvol toegevoegd." staat bovenaan',
    $overzicht !== null && str_contains($overzicht['body'], 'Het event is succesvol toegevoegd.')
);
controle('Het nieuwe event staat in het overzicht', $overzicht !== null && str_contains($overzicht['body'], $naam));

$pdo = Database::verbinding();
$rij = $pdo->prepare('SELECT id, naam, datum, locatie FROM evenement WHERE id = ?');
$rij->execute([$evenementId]);
$gevonden = $rij->fetch(PDO::FETCH_ASSOC) ?: [];

controle('Het event staat in de database', (int) ($gevonden['id'] ?? 0) === $evenementId && $evenementId > 0);
controle('Naam, datum en locatie kloppen in de database', ($gevonden['naam'] ?? '') === $naam
    && ($gevonden['datum'] ?? '') === $datum
    && ($gevonden['locatie'] ?? '') === $locatie);

echo PHP_EOL . 'UNHAPPY FLOW (ontbrekende gegevens)' . PHP_EOL;

$aantalVoor = (int) $pdo->query('SELECT COUNT(*) FROM evenement')->fetchColumn();

$leeg = verzoek($baseUrl . '/beheer/events/toevoegen', [
    'naam'                        => '',
    'datum'                       => '',
    'locatie'                     => '',
    'aantal_tickets_per_tijdslot' => '',
    'beschikbare_stands'          => '',
    'opmerking'                   => '',
]);

controle('Lege velden geven een validatiefout (422)', $leeg !== null && $leeg['status'] === 422);
controle(
    'De melding over verplichte velden staat er',
    $leeg !== null && str_contains($leeg['body'], 'Vul de gemarkeerde verplichte velden in.')
);
controle('De naam heeft een eigen veldfout', $leeg !== null && str_contains($leeg['body'], 'id="naam-fout"'));
controle('Er is niets opgeslagen', (int) $pdo->query('SELECT COUNT(*) FROM evenement')->fetchColumn() === $aantalVoor);

echo PHP_EOL . 'UNHAPPY FLOW (ongeldige gegevens)' . PHP_EOL;

$ongeldig = verzoek($baseUrl . '/beheer/events/toevoegen', [
    'naam'                        => $naam . ' ongeldig',
    'datum'                       => '2026-13-40',
    'locatie'                     => $locatie,
    'aantal_tickets_per_tijdslot' => 'abc',
    'beschikbare_stands'          => '30',
    'opmerking'                   => '',
]);

controle('Ongeldige invoer geeft een validatiefout (422)', $ongeldig !== null && $ongeldig['status'] === 422);
controle(
    'Er staat een duidelijke foutmelding',
    $ongeldig !== null && str_contains($ongeldig['body'], 'ongeldige waarde')
);
controle(
    'De datumfout noemt een geldig voorbeeld',
    $ongeldig !== null && str_contains($ongeldig['body'], 'Vul een geldige datum in')
);
controle(
    'Het ongeldige event is niet opgeslagen',
    (int) $pdo->query('SELECT COUNT(*) FROM evenement')->fetchColumn() === $aantalVoor
);

echo PHP_EOL . 'UNHAPPY FLOW (database plat)' . PHP_EOL;

$foutNaam = 'Acceptatietest DB ' . $suffix;
$pdo->exec(
    "CREATE TRIGGER trg_demo_fout_evenement BEFORE INSERT ON evenement "
    . "FOR EACH ROW SIGNAL SQLSTATE '45000' "
    . "SET MESSAGE_TEXT = 'Acceptatietest: geforceerde databasefout'"
);

try {
    $dbFout = verzoek($baseUrl . '/beheer/events/toevoegen', [
        'naam'                        => $foutNaam,
        'datum'                       => $datum,
        'locatie'                     => $locatie,
        'aantal_tickets_per_tijdslot' => '200',
        'beschikbare_stands'          => '18',
        'opmerking'                   => '',
    ]);

    controle('Een databasefout geeft HTTP 503', $dbFout !== null && $dbFout['status'] === 503);
    controle(
        'De melding "Onze excuses, er is een error. We zijn ermee bezig." staat er',
        $dbFout !== null && str_contains($dbFout['body'], 'Onze excuses, er is een error. We zijn ermee bezig.')
    );
    controle(
        'De ingevulde naam blijft behouden',
        $dbFout !== null && str_contains($dbFout['body'], 'value="' . $foutNaam . '"')
    );

    $opgeslagen = $pdo->prepare('SELECT COUNT(*) FROM evenement WHERE naam = ?');
    $opgeslagen->execute([$foutNaam]);
    controle('Er is niets opgeslagen', (int) $opgeslagen->fetchColumn() === 0);
} finally {
    $pdo->exec('DROP TRIGGER IF EXISTS trg_demo_fout_evenement');
}

if ($evenementId > 0) {
    $pdo->prepare('DELETE FROM prijs WHERE evenement_id = ?')->execute([$evenementId]);
    $pdo->prepare('DELETE FROM evenement WHERE id = ?')->execute([$evenementId]);
    echo PHP_EOL . 'Opruimen            : event ' . $evenementId . ' verwijderd' . PHP_EOL;
}

echo PHP_EOL;

if ($fouten === []) {
    echo 'Alle controles geslaagd.' . PHP_EOL;
    exit(0);
}

echo count($fouten) . ' controle(s) mislukt.' . PHP_EOL;
exit(1);
