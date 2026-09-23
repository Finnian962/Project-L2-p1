<?php

declare(strict_types=1);

/**
 * Router voor de ingebouwde PHP-devserver.
 *
 * Gebruik:  php -S localhost:8000 server.php
 *
 * Bestaande bestanden (css, js, afbeeldingen) laat de server zelf serveren;
 * alle andere URL's gaan door naar de front controller in public/index.php.
 */

$pad = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($pad !== '/' && is_file(__DIR__ . '/public' . $pad)) {
    return false;
}

require __DIR__ . '/public/index.php';
