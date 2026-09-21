<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Leest het configuratiebestand in en geeft instellingen terug via puntnotatie.
 *
 * Voorbeeld: Config::get('database.host')
 */
final class Config
{
    /** @var array<string, mixed> Alle ingelezen instellingen. */
    private static array $instellingen = [];

    /**
     * Laadt het configuratiebestand één keer in het geheugen.
     *
     * @throws RuntimeException Wanneer het configuratiebestand ontbreekt.
     */
    public static function laad(string $bestandspad): void
    {
        if (!is_file($bestandspad)) {
            throw new RuntimeException('Configuratiebestand niet gevonden: ' . $bestandspad);
        }

        /** @var array<string, mixed> $instellingen */
        $instellingen = require $bestandspad;
        self::$instellingen = $instellingen;
    }

    /**
     * Haalt een instelling op met puntnotatie.
     *
     * @param string $sleutel      Bijvoorbeeld 'database.host'.
     * @param mixed  $standaard    Waarde wanneer de sleutel niet bestaat.
     */
    public static function get(string $sleutel, mixed $standaard = null): mixed
    {
        $waarde = self::$instellingen;

        foreach (explode('.', $sleutel) as $deel) {
            if (!is_array($waarde) || !array_key_exists($deel, $waarde)) {
                return $standaard;
            }

            $waarde = $waarde[$deel];
        }

        return $waarde;
    }
}
