<?php

declare(strict_types=1);

/**
 * Controleert de drie scenario's van "Contactpersoon wijzigen".
 *
 * Happy flow:
 *   bestaande gegevens wijzigen → 302 naar /verkopers/{id}/contact?gewijzigd=1,
 *   melding "De contactpersoon is succesvol gewijzigd.", de nieuwe gegevens
 *   staan in de lijst én in de database.
 *
 * Unhappy flow (verplicht):
 *   een verplicht veld leeg → 422, "Vul de gemarkeerde verplichte velden in.",
 *   niets opgeslagen.
 *
 * Unhappy flow (ongeldig):
 *   ongeldig e-mailadres → 422, duidelijke veldfout, niets opgeslagen.
 *
 * Gebruik: php database/test-contactpersoon-wijzigen.php [baseUrl]
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
$contact = $pdo->query(
    'SELECT c.id, c.naam, c.telefoonnummer, c.emailadres, v.id AS verkoper_id, v.naam AS verkoper_naam
     FROM contactpersoon c
     INNER JOIN contact_per_verkoper cpv ON cpv.contactpersoon_id = c.id AND cpv.is_actief = 1
     INNER JOIN verkoper v ON v.id = cpv.verkoper_id AND v.is_actief = 1
     WHERE c.is_actief = 1
     ORDER BY c.id ASC
     LIMIT 1'
)->fetch(PDO::FETCH_ASSOC) ?: [];

$contactId = (int) ($contact['id'] ?? 0);
$verkoperId = (int) ($contact['verkoper_id'] ?? 0);
$origineel = [
    'naam'           => (string) ($contact['naam'] ?? ''),
    'telefoonnummer' => (string) ($contact['telefoonnummer'] ?? ''),
    'emailadres'     => (string) ($contact['emailadres'] ?? ''),
];

controle('Er is een bestaande contactpersoon om te wijzigen', $contactId > 0 && $verkoperId > 0);

if ($contactId === 0 || $verkoperId === 0) {
    echo 'FOUT: geen contactpersoon gevonden. Draai eerst php database/install.php' . PHP_EOL;
    exit(1);
}

$pad = '/verkopers/' . $verkoperId . '/contact/' . $contactId . '/wijzigen';
$nieuweNaam = 'Acceptatietest Contact ' . str_replace('.', '', uniqid('', true));
$nieuwTelefoon = '+31 6 11112222';
$nieuwEmail = 'acceptatie.' . $contactId . '@example.nl';

echo 'Contactpersoon      : ' . $origineel['naam'] . ' (id ' . $contactId . ')' . PHP_EOL;
echo 'Verkoper            : ' . ($contact['verkoper_naam'] ?? '') . ' (id ' . $verkoperId . ')' . PHP_EOL . PHP_EOL;

echo 'HAPPY FLOW' . PHP_EOL;

$formulier = verzoek($baseUrl . $pad);
controle('Het formulier laadt (HTTP 200)', $formulier !== null && $formulier['status'] === 200);

if ($formulier !== null) {
    controle(
        'Het formulier bevat naam, telefoonnummer en e-mailadres',
        str_contains($formulier['body'], 'name="naam"')
            && str_contains($formulier['body'], 'name="telefoonnummer"')
            && str_contains($formulier['body'], 'name="emailadres"')
    );
    controle(
        'De bestaande naam staat in het formulier',
        str_contains($formulier['body'], htmlspecialchars($origineel['naam'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))
    );
    controle('Er is een knop "Opslaan"', str_contains($formulier['body'], '>Opslaan<'));
}

$opslaan = verzoek($baseUrl . $pad, [
    'naam'           => $nieuweNaam,
    'telefoonnummer' => $nieuwTelefoon,
    'emailadres'     => $nieuwEmail,
]);

controle('Opslaan geeft een doorverwijzing (302)', $opslaan !== null && $opslaan['status'] === 302);

$locatieHeader = $opslaan !== null ? headerWaarde($opslaan['headers'], 'Location') : '';
controle(
    'De doorverwijzing gaat naar de contactlijst',
    str_contains($locatieHeader, '/verkopers/' . $verkoperId . '/contact')
        && str_contains($locatieHeader, 'gewijzigd=')
);

$lijst = verzoek($baseUrl . '/verkopers/' . $verkoperId . '/contact?gewijzigd=1');
controle(
    'De melding "De contactpersoon is succesvol gewijzigd." staat bovenaan',
    $lijst !== null && str_contains($lijst['body'], 'De contactpersoon is succesvol gewijzigd.')
);
controle('De nieuwe naam staat in de lijst', $lijst !== null && str_contains($lijst['body'], $nieuweNaam));
controle('Het nieuwe telefoonnummer staat in de lijst', $lijst !== null && str_contains($lijst['body'], $nieuwTelefoon));
controle('Het nieuwe e-mailadres staat in de lijst', $lijst !== null && str_contains($lijst['body'], $nieuwEmail));

$rij = $pdo->prepare('SELECT naam, telefoonnummer, emailadres FROM contactpersoon WHERE id = ?');
$rij->execute([$contactId]);
$gevonden = $rij->fetch(PDO::FETCH_ASSOC) ?: [];

controle('De nieuwe naam staat in de database', ($gevonden['naam'] ?? '') === $nieuweNaam);
controle('Het nieuwe telefoonnummer staat in de database', ($gevonden['telefoonnummer'] ?? '') === $nieuwTelefoon);
controle('Het nieuwe e-mailadres staat in de database', ($gevonden['emailadres'] ?? '') === $nieuwEmail);

echo PHP_EOL . 'UNHAPPY FLOW (ontbrekende gegevens)' . PHP_EOL;

$leeg = verzoek($baseUrl . $pad, [
    'naam'           => '',
    'telefoonnummer' => '',
    'emailadres'     => '',
]);

controle('Lege velden geven een validatiefout (422)', $leeg !== null && $leeg['status'] === 422);
controle(
    'De melding over verplichte velden staat er',
    $leeg !== null && str_contains($leeg['body'], 'Vul de gemarkeerde verplichte velden in.')
);
controle('De naam heeft een eigen veldfout', $leeg !== null && str_contains($leeg['body'], 'id="naam-fout"'));
controle('Het telefoonnummer heeft een eigen veldfout', $leeg !== null && str_contains($leeg['body'], 'id="telefoonnummer-fout"'));
controle('Het e-mailadres heeft een eigen veldfout', $leeg !== null && str_contains($leeg['body'], 'id="emailadres-fout"'));

$rij->execute([$contactId]);
$naLeeg = $rij->fetch(PDO::FETCH_ASSOC) ?: [];
controle('De wijziging is niet opgeslagen', ($naLeeg['naam'] ?? '') === $nieuweNaam);

echo PHP_EOL . 'UNHAPPY FLOW (ongeldige gegevens)' . PHP_EOL;

$ongeldig = verzoek($baseUrl . $pad, [
    'naam'           => $nieuweNaam,
    'telefoonnummer' => 'abc',
    'emailadres'     => 'geen-email',
]);

controle('Ongeldige invoer geeft een validatiefout (422)', $ongeldig !== null && $ongeldig['status'] === 422);
controle(
    'Er staat een duidelijke foutmelding',
    $ongeldig !== null && str_contains($ongeldig['body'], 'ongeldige waarde')
);
controle(
    'De e-mailfout noemt een geldig voorbeeld',
    $ongeldig !== null && str_contains($ongeldig['body'], 'Vul een geldig e-mailadres in')
);
controle(
    'De telefoonfout noemt een geldig voorbeeld',
    $ongeldig !== null && str_contains($ongeldig['body'], 'Vul een geldig telefoonnummer in')
);

$rij->execute([$contactId]);
$naOngeldig = $rij->fetch(PDO::FETCH_ASSOC) ?: [];
controle('De ongeldige wijziging is niet opgeslagen', ($naOngeldig['emailadres'] ?? '') === $nieuwEmail);

$pdo->prepare(
    'UPDATE contactpersoon SET naam = ?, telefoonnummer = ?, emailadres = ? WHERE id = ?'
)->execute([
    $origineel['naam'],
    $origineel['telefoonnummer'],
    $origineel['emailadres'],
    $contactId,
]);

echo PHP_EOL . 'Opruimen            : contactpersoon ' . $contactId . ' teruggezet' . PHP_EOL;
echo PHP_EOL;

if ($fouten === []) {
    echo 'Alle controles geslaagd.' . PHP_EOL;
    exit(0);
}

echo count($fouten) . ' controle(s) mislukt.' . PHP_EOL;
exit(1);
