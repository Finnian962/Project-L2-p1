<?php

declare(strict_types=1);

/**
 * Bootstrap van de applicatie.
 *
 * Verantwoordelijk voor: autoloading, configuratie, technische log en de
 * globale foutafhandeling. Dit bestand wordt door public/index.php geladen.
 */

use App\Core\Config;
use App\Core\Logger;

define('BASIS_MAP', dirname(__DIR__));

/**
 * PSR-4 autoloader: App\Core\Router -> app/Core/Router.php
 */
spl_autoload_register(static function (string $klasse): void {
    $prefix = 'App\\';

    if (!str_starts_with($klasse, $prefix)) {
        return;
    }

    $relatiefPad = str_replace('\\', DIRECTORY_SEPARATOR, substr($klasse, strlen($prefix)));
    $bestand = BASIS_MAP . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . $relatiefPad . '.php';

    if (is_file($bestand)) {
        require_once $bestand;
    }
});

require_once BASIS_MAP . '/app/helpers.php';

Config::laad(BASIS_MAP . '/config/config.php');
Logger::init(
    (string) Config::get('log.map', BASIS_MAP . '/storage/logs'),
    (string) Config::get('log.niveau', 'debug')
);

// PHP-waarschuwingen komen niet op het scherm maar in de technische log.
ini_set('display_errors', '0');
ini_set('log_errors', '0');
error_reporting(E_ALL);

/**
 * Zet PHP-waarschuwingen en -notices om naar logregels.
 */
set_error_handler(static function (int $niveau, string $bericht, string $bestand, int $regel): bool {
    Logger::warning('PHP-melding', [
        'niveau'  => $niveau,
        'bericht' => $bericht,
        'bestand' => $bestand . ':' . $regel,
    ]);

    return true;
});

/**
 * Laatste vangnet voor fatale fouten, zodat de bezoeker nooit een witte
 * pagina of een technische stacktrace ziet.
 */
register_shutdown_function(static function (): void {
    $fout = error_get_last();

    if ($fout === null || !in_array($fout['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }

    Logger::error('Fatale fout', [
        'bericht' => $fout['message'],
        'bestand' => $fout['file'] . ':' . $fout['line'],
    ]);
});
