<?php

declare(strict_types=1);

/**
 * Controleert beide scenario's van "Verkoper toevoegen" (/verkopers/nieuw).
 *
 * Happy flow:
 *   1. het formulier laadt met de velden naam, status, e-mailadres en
 *      telefoonnummer;
 *   2. opslaan stuurt door (302, Post/Redirect/Get) naar
 *      /verkopers?toegevoegd={verkopernummer};
 *   3. daar staat de melding "De verkoper is succesvol toegevoegd.";
 *   4. de nieuwe verkoper staat als kaart in het overzicht;
 *   5. het verkopernummer is automatisch gegenereerd en er hoort een
 *      contactpersoon bij de verkoper.
 *
 * Unhappy flow:
 *   1. lege velden -> 422 met een melding per veld, er wordt niets opgeslagen;
 *   2. een databasefout -> 500 met "Er is een fout opgetreden bij het opslaan
 *      van de verkoper. Probeer het later opnieuw.", alle ingevulde gegevens
 *      blijven in het formulier staan en er wordt niets weggeschreven
 *      (rollback). De fout wordt geforceerd met dezelfde trigger als
 *      `php database/demo_unhappy.php uit verkoper`.
 *
 * Alles wat deze test aanmaakt wordt na afloop weer verwijderd en de trigger
 * waarmee de databasefout wordt geforceerd verdwijnt altijd, ook bij een fout.
 *
 * Gebruik: php database/test-verkoper-toevoegen.php [baseUrl]
 *          standaard http://127.0.0.1:8090 (terugval op 8000)
 *
 * De server moet draaien; de databaseverbinding komt uit config/config.php.
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;

/** @var list<string> $fouten */
$fouten = [];

/**
 * Voert een HTTP-verzoek uit zonder zelf door te verwijzen.
 *
 * @param  array<string, string> $velden
 * @return array{status: int, headers: list<string>, body: string}|null
 */
function verzoek(string $url, array $velden = []): ?array
{
    $isPost = $velden !== [];

    $opties = [
        'http' => [
            'method'         => $isPost ? 'POST' : 'GET',
            'header'         => 'Content-Type: application/x-www-form-urlencoded',
            'content'        => http_build_query($velden),
            'ignore_errors'  => true,   // ook 4xx/5xx en de body ophalen
            'follow_location' => 0,     // de doorverwijzing zelf controleren
            'timeout'        => 15,
        ],
    ];

    $context = stream_context_create($opties);
    $body = @file_get_contents($url, false, $context);

    if ($body === false || !isset($http_response_header[0])) {
        return null;
    }

    preg_match('#HTTP/\S+\s+(\d{3})#', $http_response_header[0], $treffer);

    return [
        'status'  => (int) ($treffer[1] ?? 0),
        'headers' => $http_response_header,
        'body'    => $body,
    ];
}

/** Leest een header uit een respons (case-ongevoelig). */
function headerWaarde(array $respons, string $naam): string
{
    foreach ($respons['headers'] as $regel) {
        if (stripos($regel, $naam . ':') === 0) {
            return trim(substr($regel, strlen($naam) + 1));
        }
    }

    return '';
}

/** Controleert een voorwaarde en rapporteert het resultaat. */
function controle(string $omschrijving, bool $ok): void
{
    echo ($ok ? '  OK   ' : '  FOUT ') . $omschrijving . PHP_EOL;

    if (!$ok) {
        $GLOBALS['fouten'][] = $omschrijving;
    }
}

// --- De server vinden -------------------------------------------------------

$baseUrl = $argv[1] ?? '';

if ($baseUrl === '') {
    foreach (['http://127.0.0.1:8090', 'http://127.0.0.1:8000'] as $kandidaat) {
        if (verzoek($kandidaat . '/') !== null) {
            $baseUrl = $kandidaat;
            break;
        }
    }
}

if ($baseUrl === '') {
    echo 'FOUT: de server is niet bereikbaar op poort 8090 of 8000.' . PHP_EOL;
    echo 'Start de server eerst met: php -S 127.0.0.1:8090 -t public server.php' . PHP_EOL;

    exit(1);
}

echo 'Server              : ' . $baseUrl . PHP_EOL;

// --- Happy flow -------------------------------------------------------------

$suffix = str_replace('.', '', uniqid('', true));
$naam = 'Acceptatietest ' . $suffix;
$email = 'acceptatie-' . $suffix . '@voorbeeld.nl';
$telefoon = '06' . substr($suffix, -8);

echo 'Nieuwe verkoper     : ' . $naam . PHP_EOL . PHP_EOL;

echo 'HAPPY FLOW' . PHP_EOL;

$formulier = verzoek($baseUrl . '/verkopers/nieuw');
controle('Het formulier laadt (HTTP 200)', $formulier !== null && $formulier['status'] === 200);

if ($formulier !== null) {
    controle(
        'Het formulier bevat naam, status, e-mailadres en telefoonnummer',
        str_contains($formulier['body'], 'name="naam"')
            && str_contains($formulier['body'], 'name="status"')
            && str_contains($formulier['body'], 'name="emailadres"')
            && str_contains($formulier['body'], 'name="telefoonnummer"')
    );
    controle('Er is een knop "Opslaan"', str_contains($formulier['body'], '>Opslaan<'));
}

$opslaan = verzoek($baseUrl . '/verkopers/nieuw', [
    'naam'           => $naam,
    'status'         => '1',
    'emailadres'     => $email,
    'telefoonnummer' => $telefoon,
]);

controle('Opslaan geeft een doorverwijzing (302)', $opslaan !== null && $opslaan['status'] === 302);

$locatie = $opslaan !== null ? headerWaarde($opslaan, 'Location') : '';
controle('De doorverwijzing gaat naar het overzicht met een verkopernummer', str_contains($locatie, 'toegevoegd='));

$verkoperId = 0;

if (preg_match('/toegevoegd=(\d+)/', $locatie, $treffer) === 1) {
    $verkoperId = (int) $treffer[1];
}

controle('Het verkopernummer is automatisch gegenereerd (' . ($verkoperId ?: 'geen') . ')', $verkoperId > 0);

$overzicht = verzoek($baseUrl . '/verkopers?toegevoegd=' . ($verkoperId ?: 0));
$meldingOk = $overzicht !== null
    && str_contains($overzicht['body'], 'De verkoper is succesvol toegevoegd.');

controle('De melding "De verkoper is succesvol toegevoegd." staat bovenaan', $meldingOk);
controle('De nieuwe verkoper staat in het overzicht', $overzicht !== null && str_contains($overzicht['body'], $naam));

// --- Controle in de database + opruimen -------------------------------------

$pdo = Database::verbinding();

$rij = $pdo->prepare('SELECT id FROM verkoper WHERE naam = ? ORDER BY id DESC LIMIT 1');
$rij->execute([$naam]);
$gevonden = (int) ($rij->fetchColumn() ?: 0);

controle('De verkoper staat in de database met dat verkopernummer', $gevonden === $verkoperId && $verkoperId > 0);

$koppel = $pdo->prepare(
    'SELECT COUNT(*) FROM contact_per_verkoper k
     INNER JOIN contactpersoon c ON c.id = k.contactpersoon_id
     WHERE k.verkoper_id = ? AND c.emailadres = ?'
);
$koppel->execute([$verkoperId, $email]);
controle('Er hoort een contactpersoon bij de verkoper', (int) $koppel->fetchColumn() === 1);

// Opruimen, ook wanneer een controle is gefaald.
if ($verkoperId > 0) {
    $pdo->prepare('DELETE FROM contact_per_verkoper WHERE verkoper_id = ?')->execute([$verkoperId]);
    $pdo->prepare('DELETE FROM contactpersoon WHERE emailadres = ?')->execute([$email]);
    $pdo->prepare('DELETE FROM verkoper WHERE id = ?')->execute([$verkoperId]);
}

echo PHP_EOL . 'Opruimen            : verkoper ' . $verkoperId . ' verwijderd' . PHP_EOL;

// --- Unhappy flow 1: ongeldige invoer ---------------------------------------

echo PHP_EOL . 'UNHAPPY FLOW (ongeldige invoer)' . PHP_EOL;

$leeg = verzoek($baseUrl . '/verkopers/nieuw', [
    'naam'           => '',
    'status'         => '0',
    'emailadres'     => '',
    'telefoonnummer' => '',
]);

controle('Lege velden geven een validatiefout (422)', $leeg !== null && $leeg['status'] === 422);
controle(
    'De melding "Vul de gemarkeerde verplichte velden in." staat er',
    $leeg !== null && str_contains($leeg['body'], 'Vul de gemarkeerde verplichte velden in.')
);
controle('De naam heeft een eigen veldfout', $leeg !== null && str_contains($leeg['body'], 'id="naam-fout"'));
controle('Het formulier blijft staan', $leeg !== null && str_contains($leeg['body'], 'name="naam"'));

$legeNamen = $pdo->query("SELECT COUNT(*) FROM verkoper WHERE naam = ''")->fetchColumn();
controle('Er is niets opgeslagen', (int) $legeNamen === 0);

// --- Unhappy flow 2: databasefout -------------------------------------------

echo PHP_EOL . 'UNHAPPY FLOW (databasefout)' . PHP_EOL;

$foutNaam = 'Acceptatietest fout ' . $suffix;
$foutEmail = 'fout-' . $suffix . '@voorbeeld.nl';

// Dezelfde trigger als `php database/demo_unhappy.php uit verkoper`.
$pdo->exec(
    "CREATE TRIGGER trg_demo_fout_verkoper BEFORE INSERT ON verkoper "
    . "FOR EACH ROW SIGNAL SQLSTATE '45000' "
    . "SET MESSAGE_TEXT = 'Acceptatietest: geforceerde databasefout'"
);

try {
    $foutPost = verzoek($baseUrl . '/verkopers/nieuw', [
        'naam'           => $foutNaam,
        'status'         => '1',
        'emailadres'     => $foutEmail,
        'telefoonnummer' => $telefoon,
    ]);

    controle('Een databasefout geeft HTTP 500', $foutPost !== null && $foutPost['status'] === 500);
    controle(
        'De exacte foutmelding staat er',
        $foutPost !== null && str_contains(
            $foutPost['body'],
            'Er is een fout opgetreden bij het opslaan van de verkoper. Probeer het later opnieuw.'
        )
    );
    controle(
        'De ingevulde naam blijft behouden',
        $foutPost !== null && str_contains($foutPost['body'], 'value="' . $foutNaam . '"')
    );
    controle(
        'Het e-mailadres blijft behouden',
        $foutPost !== null && str_contains($foutPost['body'], 'value="' . $foutEmail . '"')
    );
    controle(
        'Het telefoonnummer blijft behouden',
        $foutPost !== null && str_contains($foutPost['body'], 'value="' . $telefoon . '"')
    );
    controle(
        'De status blijft behouden (Partner geselecteerd)',
        $foutPost !== null && str_contains($foutPost['body'], '<option value="1" selected>')
    );
    controle('Het formulier wordt opnieuw getoond', $foutPost !== null && str_contains($foutPost['body'], 'name="naam"'));

    $opgeslagen = $pdo->prepare('SELECT COUNT(*) FROM verkoper WHERE naam = ?');
    $opgeslagen->execute([$foutNaam]);
    controle('Er is niets opgeslagen (rollback)', (int) $opgeslagen->fetchColumn() === 0);
} finally {
    // De trigger verdwijnt altijd, anders blijft de site in de foutmodus staan.
    $pdo->exec('DROP TRIGGER IF EXISTS trg_demo_fout_verkoper');
}

$trigger = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.triggers
     WHERE trigger_schema = DATABASE() AND trigger_name = 'trg_demo_fout_verkoper'"
)->fetchColumn();

controle('De trigger is weer verwijderd', (int) $trigger === 0);

// --- Resultaat --------------------------------------------------------------

echo PHP_EOL;

if ($fouten === []) {
    echo 'Alle controles geslaagd: beide scenario\'s van verkoper toevoegen werken.' . PHP_EOL;
    exit(0);
}

echo count($fouten) . ' controle(s) gefaald:' . PHP_EOL;

foreach ($fouten as $fout) {
    echo '  - ' . $fout . PHP_EOL;
}

exit(1);
