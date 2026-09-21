<?php

declare(strict_types=1);

namespace App\Entities;

/**
 * Een verkoper op het evenement: shop, privéverkoper, partner of side-stand.
 */
final class Verkoper
{
    public function __construct(
        public readonly int $id,
        public readonly string $naam,
        public readonly bool $isPartner,
        public readonly string $verkooptSoort,
        public readonly string $standType,
        public readonly int $dagen,
        public readonly ?string $logo,
        public readonly ?string $beschrijving,
        public readonly string $evenementNaam,
        public readonly int $aantalStands,
        public readonly int $aantalContactpersonen
    ) {
    }

    /**
     * Zet één databaserij om naar een Verkoper-object.
     *
     * @param array<string, mixed> $rij
     */
    public static function vanRij(array $rij): self
    {
        return new self(
            (int) ($rij['id'] ?? 0),
            (string) ($rij['naam'] ?? ''),
            (bool) ($rij['speciale_status'] ?? false),
            (string) ($rij['verkoopt_soort'] ?? ''),
            (string) ($rij['stand_type'] ?? ''),
            (int) ($rij['dagen'] ?? 1),
            isset($rij['logo']) ? (string) $rij['logo'] : null,
            isset($rij['beschrijving']) ? (string) $rij['beschrijving'] : null,
            (string) ($rij['evenement_naam'] ?? ''),
            (int) ($rij['aantal_stands'] ?? 0),
            (int) ($rij['aantal_contactpersonen'] ?? 0)
        );
    }

    /** Pad naar het partnerlogo, of null wanneer er geen logo is. */
    public function logoPad(): ?string
    {
        if ($this->logo === null || $this->logo === '') {
            return null;
        }

        return asset('img/partners/' . $this->logo);
    }

    /** Initialen als fallback wanneer een verkoper geen logo heeft. */
    public function initialen(): string
    {
        $woorden = preg_split('/\s+/', trim($this->naam)) ?: [];
        $initialen = '';

        foreach (array_slice($woorden, 0, 2) as $woord) {
            $initialen .= mb_strtoupper(mb_substr($woord, 0, 1));
        }

        return $initialen === '' ? '?' : $initialen;
    }

    public function dagenOmschrijving(): string
    {
        return $this->dagen >= 2 ? 'Beide dagen' : 'Eén dag';
    }
}
