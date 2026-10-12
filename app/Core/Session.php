<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Beheert de PHP-sessie: de loginstatus van de gebruiker en de
 * wachtmeldingen (flash messages) die na een redirect getoond worden.
 *
 * Elke applicatievraag start de sessie via Session::start(); daarna kunnen
 * controllers en views veilig lezen en schrijven.
 */
final class Session
{
    private const NAAM = 'sneakerness';
    private const MELDING_SLEUTEL = '_melding';

    /**
     * Start de sessie. De methode is idempotent: meerdere aanroepen binnen
     * één verzoek zijn ongevaarlijk.
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (PHP_SAPI === 'cli') {
            // Zonder webserver is er geen cookie; $_SESSION blijft gewoon
            // beschikbaar als array voor scripts en tests.
            return;
        }

        session_name(self::NAAM);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    public static function zet(string $sleutel, mixed $waarde): void
    {
        self::start();
        $_SESSION[$sleutel] = $waarde;
    }

    public static function haal(string $sleutel, mixed $standaard = null): mixed
    {
        self::start();

        return $_SESSION[$sleutel] ?? $standaard;
    }

    public static function verwijder(string $sleutel): void
    {
        self::start();
        unset($_SESSION[$sleutel]);
    }

    /**
     * Vernieuwt de sessie-id na een gelukte inlogpoging (tegen session fixing).
     */
    public static function vernieuw(): void
    {
        self::start();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Zet een melding die pas bij het volgende verzoek getoond wordt.
     *
     * @param string $soort info | waarschuwing | fout
     */
    public static function melding(string $tekst, string $soort = 'info', ?string $kop = null): void
    {
        self::zet(self::MELDING_SLEUTEL, [
            'tekst' => $tekst,
            'soort' => $soort,
            'kop'   => $kop,
        ]);
    }

    /**
     * Haalt de wachtmelding op én wist deze, zodat hij maar één keer verschijnt.
     *
     * @return array{tekst: string, soort: string, kop: string|null}|null
     */
    public static function haalMelding(): ?array
    {
        $melding = self::haal(self::MELDING_SLEUTEL);

        if (!is_array($melding) || !isset($melding['tekst'])) {
            return null;
        }

        self::verwijder(self::MELDING_SLEUTEL);

        return [
            'tekst' => (string) $melding['tekst'],
            'soort' => (string) ($melding['soort'] ?? 'info'),
            'kop'   => isset($melding['kop']) ? (string) $melding['kop'] : null,
        ];
    }

    /** Wist de hele sessie (gebruikt bij uitloggen). */
    public static function leeg(): void
    {
        self::start();
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
