<?php

declare(strict_types=1);

/**
 * Controleert de drie scenario's van "Event wijzigen".
 *
 * Happy flow:
 *   bestaande gegevens wijzigen → 302 naar /events?gewijzigd={id},
 *   melding "Het event is succesvol gewijzigd.", de nieuwe gegevens staan
 *   in het overzicht én in de database.
 *
 * Unhappy flow (verplicht):
 *   een verplicht veld leeg → 422, "Vul de gemarkeerde verplichte velden in.",
 *   niets opgeslagen.
 *
 * Unhappy flow (ongeldig):
 *   ongeldige datum → 422, duidelijke veldfout, niets opgeslagen.
 *
 * Unhappy flow (database plat):
 *   UPDATE faalt → 503, "Onze excuses, de database is tijdelijk niet
 *   bereikbaar. We zijn ermee bezig.", niets opgeslagen.
 *
 * Gebruik: php database/test-event-wijzigen.php [baseUrl]
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

function alsTekst(mixed $waarde): string
{
    if ($waarde instanceof DateTimeInterface) {
        return $waarde->format('Y-m-d');
    }

    $tekst = (string) $waarde;

    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $tekst, $treffer) === 1) {
        return $treffer[0];
    }

    return $tekst;
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
    echo 'Start de server eerst met: php -S 127.0.0.1:8080 server.php' . PHP_EOL;

    exit(1);
}

echo 'Server              : ' . $baseUrl . PHP_EOL;

$inloggen = verzoek($baseUrl . '/inloggen', [
    'gebruikersnaam' => 'noah',
    'wachtwoord'     => 'Sneakerness2026!',
]);

controle('Inloggen als beheerder geeft een doorverwijzing', $inloggen !== null && $inloggen['status'] === 302);

$pdo = Database::verbinding();
$event = $pdo->query(
    'SELECT id, naam, datum, locatie, aantal_tickets_per_tijdslot, beschikbare_stands, opmerking
     FROM evenement
     WHERE is_actief = 1
     ORDER BY id ASC
     LIMIT 1'
)->fetch(PDO::FETCH_ASSOC) ?: [];

$evenementId = (int) ($event['id'] ?? 0);
$origineel = [
    'naam'                        => (string) ($event['naam'] ?? ''),
    'datum'                       => alsTekst($event['datum'] ?? ''),
    'locatie'                     => (string) ($event['locatie'] ?? ''),
    'aantal_tickets_per_tijdslot' => (string) ($event['aantal_tickets_per_tijdslot'] ?? '0'),
    'beschikbare_stands'          => (string) ($event['beschikbare_stands'] ?? '0'),
    'opmerking'                   => (string) ($event['opmerking'] ?? ''),
];

controle('Er is een bestaand event om te wijzigen', $evenementId > 0);

if ($evenementId === 0) {
    echo 'FOUT: geen event gevonden. Draai eerst php database/install.php' . PHP_EOL;
    exit(1);
}

$pad = '/beheer/events/' . $evenementId . '/wijzigen';
$nieuweNaam = 'Acceptatietest Event wijzigen ' . str_replace('.', '', uniqid('', true));
$nieuweDatum = '2027-09-18';
$nieuweLocatie = 'Testlocatie, Rotterdam';

echo 'Event               : ' . $origineel['naam'] . ' (id ' . $evenementId . ')' . PHP_EOL . PHP_EOL;

echo 'HAPPY FLOW' . PHP_EOL;

$formulier = verzoek($baseUrl . $pad);
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
    controle(
        'De bestaande naam staat in het formulier',
        str_contains(
            $formulier['body'],
            htmlspecialchars($origineel['naam'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        )
    );
    controle('Er is een knop "Opslaan"', str_contains($formulier['body'], '>Opslaan<'));
}

$opslaan = verzoek($baseUrl . $pad, [
    'naam'                        => $nieuweNaam,
    'datum'                       => $nieuweDatum,
    'locatie'                     => $nieuweLocatie,
    'aantal_tickets_per_tijdslot' => '180',
    'beschikbare_stands'          => '22',
    'opmerking'                   => 'Gewijzigd door de acceptatietest',
]);

controle('Opslaan geeft een doorverwijzing (302)', $opslaan !== null && $opslaan['status'] === 302);

$locatieHeader = $opslaan !== null ? headerWaarde($opslaan['headers'], 'Location') : '';
controle(
    'De doorverwijzing gaat naar het eventoverzicht',
    str_contains($locatieHeader, '/events') && str_contains($locatieHeader, 'gewijzigd=')
);

$overzicht = verzoek($baseUrl . '/events?gewijzigd=' . $evenementId);
controle(
    'De melding "Het event is succesvol gewijzigd." staat bovenaan',
    $overzicht !== null && str_contains($overzicht['body'], 'Het event is succesvol gewijzigd.')
);
controle('De nieuwe naam staat in het overzicht', $overzicht !== null && str_contains($overzicht['body'], $nieuweNaam));
controle('De nieuwe locatie staat in het overzicht', $overzicht !== null && str_contains($overzicht['body'], $nieuweLocatie));

$rij = $pdo->prepare(
    'SELECT naam, datum, locatie, aantal_tickets_per_tijdslot, beschikbare_stands, opmerking
     FROM evenement WHERE id = ?'
);
$rij->execute([$evenementId]);
$gevonden = $rij->fetch(PDO::FETCH_ASSOC) ?: [];

controle('De nieuwe naam staat in de database', ($gevonden['naam'] ?? '') === $nieuweNaam);
controle('De nieuwe datum staat in de database', alsTekst($gevonden['datum'] ?? '') === $nieuweDatum);
controle('De nieuwe locatie staat in de database', ($gevonden['locatie'] ?? '') === $nieuweLocatie);
controle('Tickets per tijdslot zijn bijgewerkt', (string) ($gevonden['aantal_tickets_per_tijdslot'] ?? '') === '180');
controle('Beschikbare stands zijn bijgewerkt', (string) ($gevonden['beschikbare_stands'] ?? '') === '22');

echo PHP_EOL . 'UNHAPPY FLOW (ontbrekende gegevens)' . PHP_EOL;

$leeg = verzoek($baseUrl . $pad, [
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

$rij->execute([$evenementId]);
$naLeeg = $rij->fetch(PDO::FETCH_ASSOC) ?: [];
controle('De wijziging is niet opgeslagen', ($naLeeg['naam'] ?? '') === $nieuweNaam);

echo PHP_EOL . 'UNHAPPY FLOW (ongeldige gegevens)' . PHP_EOL;

$ongeldig = verzoek($baseUrl . $pad, [
    'naam'                        => $nieuweNaam . ' ongeldig',
    'datum'                       => '2026-13-40',
    'locatie'                     => $nieuweLocatie,
    'aantal_tickets_per_tijdslot' => 'abc',
    'beschikbare_stands'          => '22',
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

$rij->execute([$evenementId]);
$naOngeldig = $rij->fetch(PDO::FETCH_ASSOC) ?: [];
controle('De ongeldige wijziging is niet opgeslagen', ($naOngeldig['naam'] ?? '') === $nieuweNaam);

echo PHP_EOL . 'UNHAPPY FLOW (database plat)' . PHP_EOL;

$foutNaam = 'Acceptatietest DB wijzigen ' . str_replace('.', '', uniqid('', true));
$pdo->exec('DROP TRIGGER IF EXISTS trg_demo_fout_evenement_wijzigen');
$pdo->exec(
    "CREATE TRIGGER trg_demo_fout_evenement_wijzigen BEFORE UPDATE ON evenement "
    . "FOR EACH ROW SIGNAL SQLSTATE '45000' "
    . "SET MESSAGE_TEXT = 'Acceptatietest: geforceerde databasefout bij wijzigen'"
);

try {
    $dbFout = verzoek($baseUrl . $pad, [
        'naam'                        => $foutNaam,
        'datum'                       => $nieuweDatum,
        'locatie'                     => $nieuweLocatie,
        'aantal_tickets_per_tijdslot' => '180',
        'beschikbare_stands'          => '22',
        'opmerking'                   => '',
    ]);

    controle('Een databasefout geeft HTTP 503', $dbFout !== null && $dbFout['status'] === 503);
    controle(
        'De melding "Onze excuses, de database is tijdelijk niet bereikbaar." staat er',
        $dbFout !== null && str_contains(
            $dbFout['body'],
            'Onze excuses, de database is tijdelijk niet bereikbaar. We zijn ermee bezig.'
        )
    );
    controle(
        'De ingevulde naam blijft behouden',
        $dbFout !== null && str_contains($dbFout['body'], 'value="' . $foutNaam . '"')
    );

    $rij->execute([$evenementId]);
    $naDbFout = $rij->fetch(PDO::FETCH_ASSOC) ?: [];
    controle('Er is niets opgeslagen', ($naDbFout['naam'] ?? '') === $nieuweNaam);
} finally {
    $pdo->exec('DROP TRIGGER IF EXISTS trg_demo_fout_evenement_wijzigen');
}

$pdo->prepare(
    'UPDATE evenement
     SET naam = ?, datum = ?, locatie = ?, aantal_tickets_per_tijdslot = ?, beschikbare_stands = ?, opmerking = ?
     WHERE id = ?'
)->execute([
    $origineel['naam'],
    $origineel['datum'],
    $origineel['locatie'],
    (int) $origineel['aantal_tickets_per_tijdslot'],
    (int) $origineel['beschikbare_stands'],
    $origineel['opmerking'] === '' ? null : $origineel['opmerking'],
    $evenementId,
]);

echo PHP_EOL . 'Opruimen            : event ' . $evenementId . ' teruggezet' . PHP_EOL;
echo PHP_EOL;

if ($fouten === []) {
    echo 'Alle controles geslaagd.' . PHP_EOL;
    exit(0);
}

echo count($fouten) . ' controle(s) mislukt.' . PHP_EOL;
exit(1);
