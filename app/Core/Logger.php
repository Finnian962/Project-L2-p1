<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Eenvoudige technische log.
 *
 * Elke regel bevat tijdstip, niveau, bericht, context en het IP-adres van de
 * bezoeker. De log wordt per dag in een apart bestand geschreven zodat de
 * organisator snel kan terugzoeken wat er op een bepaalde dag is gebeurd.
 *
 * De logregels zijn zichtbaar in de applicatie via /beheer/log.
 */
final class Logger
{
    public const NIVEAU_DEBUG   = 'debug';
    public const NIVEAU_INFO    = 'info';
    public const NIVEAU_WARNING = 'warning';
    public const NIVEAU_ERROR   = 'error';

    /** Volgorde van de niveaus; alles onder het ingestelde niveau wordt genegeerd. */
    private const NIVEAU_GEWICHT = [
        self::NIVEAU_DEBUG   => 10,
        self::NIVEAU_INFO    => 20,
        self::NIVEAU_WARNING => 30,
        self::NIVEAU_ERROR   => 40,
    ];

    private static string $logmap = '';

    private static string $minimumNiveau = self::NIVEAU_DEBUG;

    /**
     * Stelt de logmap in en maakt deze aan wanneer die nog niet bestaat.
     */
    public static function init(string $logmap, string $minimumNiveau = self::NIVEAU_DEBUG): void
    {
        self::$logmap = rtrim($logmap, '/\\');
        self::$minimumNiveau = array_key_exists($minimumNiveau, self::NIVEAU_GEWICHT)
            ? $minimumNiveau
            : self::NIVEAU_DEBUG;

        if (!is_dir(self::$logmap)) {
            @mkdir(self::$logmap, 0777, true);
        }
    }

    /** @param array<string, mixed> $context */
    public static function debug(string $bericht, array $context = []): void
    {
        self::schrijf(self::NIVEAU_DEBUG, $bericht, $context);
    }

    /** @param array<string, mixed> $context */
    public static function info(string $bericht, array $context = []): void
    {
        self::schrijf(self::NIVEAU_INFO, $bericht, $context);
    }

    /** @param array<string, mixed> $context */
    public static function warning(string $bericht, array $context = []): void
    {
        self::schrijf(self::NIVEAU_WARNING, $bericht, $context);
    }

    /** @param array<string, mixed> $context */
    public static function error(string $bericht, array $context = []): void
    {
        self::schrijf(self::NIVEAU_ERROR, $bericht, $context);
    }

    /**
     * Logt een exception inclusief bestand, regelnummer en stacktrace-top.
     */
    public static function exception(Throwable $fout, string $bericht = 'Onverwachte fout'): void
    {
        self::error($bericht, [
            'type'     => $fout::class,
            'melding'  => $fout->getMessage(),
            'bestand'  => $fout->getFile() . ':' . $fout->getLine(),
            'code'     => $fout->getCode(),
        ]);
    }

    /**
     * Geeft de laatste regels van de log terug (nieuwste eerst).
     *
     * @return list<string>
     */
    public static function laatsteRegels(int $aantal = 100): array
    {
        $bestand = self::bestandsnaam();

        if (!is_file($bestand)) {
            return [];
        }

        $regels = file($bestand, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($regels === false) {
            return [];
        }

        return array_reverse(array_slice($regels, -$aantal));
    }

    /**
     * Schrijft één regel naar het logbestand van vandaag.
     *
     * @param array<string, mixed> $context
     */
    private static function schrijf(string $niveau, string $bericht, array $context): void
    {
        if (self::NIVEAU_GEWICHT[$niveau] < self::NIVEAU_GEWICHT[self::$minimumNiveau]) {
            return;
        }

        if (self::$logmap === '') {
            return;
        }

        $regel = sprintf(
            '[%s] %-7s %s | ip=%s | %s%s',
            date('Y-m-d H:i:s'),
            strtoupper($niveau),
            $bericht,
            $_SERVER['REMOTE_ADDR'] ?? 'cli',
            $context === [] ? '-' : json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            PHP_EOL
        );

        @file_put_contents(self::bestandsnaam(), $regel, FILE_APPEND | LOCK_EX);
    }

    /** Pad naar het logbestand van vandaag. */
    private static function bestandsnaam(): string
    {
        return self::$logmap . DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.log';
    }
}
