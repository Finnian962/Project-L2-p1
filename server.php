<?php

declare(strict_types=1);

/**
 * Router voor de ingebouwde PHP-devserver.
 *
 * Gebruik:  php -S localhost:8000 server.php
 *
 * Bestaande bestanden (css, js, afbeeldingen) worden hier zelf gestuurd;
 * alle andere URL's gaan door naar de front controller in public/index.php.
 *
 * Let op: de ingebouwde server serveert vanaf de projectmap, terwijl de
 * statische bestanden in public/ liggen. Daarom geven we "return false"
 * niet door aan de server (dat levert een 404 op), maar lezen we het
 * bestand zelf uit public/ met het juiste content-type.
 */

$pad = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$mimeTypes = [
    'css'   => 'text/css; charset=utf-8',
    'js'    => 'application/javascript; charset=utf-8',
    'json'  => 'application/json; charset=utf-8',
    'svg'   => 'image/svg+xml',
    'png'   => 'image/png',
    'jpg'   => 'image/jpeg',
    'jpeg'  => 'image/jpeg',
    'gif'   => 'image/gif',
    'webp'  => 'image/webp',
    'ico'   => 'image/x-icon',
    'woff'  => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf'   => 'font/ttf',
];

$publicMap = realpath(__DIR__ . '/public');
$bestand = $pad === '/' ? false : realpath(__DIR__ . '/public' . $pad);

// Alleen bestanden ín public/ worden gestuurd: zo kan er geen pad
// ("../../config/config.php") uit de applicatiemap gelekt worden.
if (
    $publicMap !== false
    && $bestand !== false
    && is_file($bestand)
    && str_starts_with($bestand, $publicMap . DIRECTORY_SEPARATOR)
) {
    $omvang = filesize($bestand);
    $extensie = strtolower(pathinfo($bestand, PATHINFO_EXTENSION));

    header('Content-Type: ' . ($mimeTypes[$extensie] ?? 'application/octet-stream'));

    if ($omvang !== false) {
        header('Content-Length: ' . $omvang);
    }

    readfile($bestand);
    exit;
}

require __DIR__ . '/public/index.php';
