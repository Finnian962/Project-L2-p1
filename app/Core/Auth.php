<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Houdt bij wie er ingelogd is en welke rol die gebruiker heeft.
 *
 * De gegevens staan in de sessie (zie Session); de wachtwoordcontrole zelf
 * gebeurt in GebruikerModel, zodat Auth alleen maar hoeft te onthouden wie
 * er is.
 *
 * Rollen:
 *   beheerder   -> organisator, beheert events en stands;
 *   standhouder -> verkoper die een stand reserveert.
 */
final class Auth
{
    private const SLEUTEL = 'gebruiker';

    /** Zet het ingelogde account in de sessie. */
    public static function inloggen(array $gebruiker): void
    {
        Session::vernieuw(); // nieuwe sessie-id na een gelukte inlogpoging

        Session::zet(self::SLEUTEL, [
            'id'             => (int) ($gebruiker['id'] ?? 0),
            'naam'           => (string) ($gebruiker['naam'] ?? ''),
            'gebruikersnaam' => (string) ($gebruiker['gebruikersnaam'] ?? ''),
            'rol'            => (string) ($gebruiker['rol'] ?? ''),
            'verkoper_id'    => (int) ($gebruiker['verkoper_id'] ?? 0),
        ]);
    }

    /** Verwijdert het account uit de sessie (uitloggen). */
    public static function uitloggen(): void
    {
        Session::verwijder(self::SLEUTEL);
    }

    public static function ingelogd(): bool
    {
        return Session::haal(self::SLEUTEL) !== null;
    }

    /**
     * Het ingelogde account.
     *
     * @return array{id: int, naam: string, gebruikersnaam: string, rol: string, verkoper_id: int}|null
     */
    public static function gebruiker(): ?array
    {
        $gebruiker = Session::haal(self::SLEUTEL);

        return is_array($gebruiker) ? $gebruiker : null;
    }

    /** Naam van de ingelogde gebruiker, voor de navigatie en meldingen. */
    public static function naam(): string
    {
        return (string) (self::gebruiker()['naam'] ?? '');
    }

    /** De inlognaam (gebruikersnaam) van de ingelogde gebruiker. */
    public static function gebruikersnaam(): string
    {
        return (string) (self::gebruiker()['gebruikersnaam'] ?? '');
    }

    public static function rol(): string
    {
        return (string) (self::gebruiker()['rol'] ?? '');
    }

    /** Id van de verkoper die aan de standhouder gekoppeld is (0 = geen). */
    public static function verkoperId(): int
    {
        return (int) (self::gebruiker()['verkoper_id'] ?? 0);
    }

    /** Controleert of de ingelogde gebruiker minstens één van de rollen heeft. */
    public static function heeftRol(string ...$rollen): bool
    {
        if (!self::ingelogd()) {
            return false;
        }

        return in_array(self::rol(), $rollen, true);
    }
}
