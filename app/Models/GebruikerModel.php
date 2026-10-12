<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Logger;
use App\Core\Model;

/**
 * Leest inlogaccounts en controleert de inloggegevens.
 *
 * De wachtwoorden staan als bcrypt-hash in de database; de vergelijking
 * gebeurt met password_verify(). Bij een onbekende gebruikersnaam wordt het
 * wachtwoord alsnog gecontroleerd tegen een vaste dummy-hash, zodat een
 * aanvaller niet aan de reactietijd kan zien of een account bestaat.
 */
final class GebruikerModel extends Model
{
    /** Dummy-hash met hetzelfde algoritme en kostenplaatje als de echte hashes. */
    private const DUMMY_HASH = '$2y$12$yNGW3oTHJk0xWLkJvkk.DuFmEJUXDSwfX2gfUxGSe/yB8ZM13jtjO';

    /**
     * Zoekt een actief account op gebruikersnaam, inclusief wachtwoordhash.
     *
     * @return array<string, mixed>|null Null wanneer het account niet bestaat.
     */
    public function haalAccount(string $gebruikersnaam): ?array
    {
        return $this->roepProcedureAanVoorEenRij('sp_gebruiker_inloggen', [$gebruikersnaam]);
    }

    /**
     * Controleert gebruikersnaam en wachtwoord.
     *
     * @return array<string, mixed>|null Het account zonder hash bij een
     *                                    geldige combinatie, anders null.
     */
    public function inloggen(string $gebruikersnaam, string $wachtwoord): ?array
    {
        $account = $this->haalAccount($gebruikersnaam);

        $hash = is_array($account) && isset($account['wachtwoord'])
            ? (string) $account['wachtwoord']
            : self::DUMMY_HASH;

        $klopt = password_verify($wachtwoord, $hash);

        if ($account === null || !$klopt) {
            Logger::warning('Mislukte inlogpoging', [
                'gebruikersnaam' => $gebruikersnaam,
                'accountBestaat' => $account !== null,
            ]);

            return null;
        }

        unset($account['wachtwoord']);

        Logger::info('Gebruiker ingelogd', [
            'gebruikersnaam' => $gebruikersnaam,
            'rol'            => $account['rol'] ?? null,
        ]);

        return $account;
    }
}
