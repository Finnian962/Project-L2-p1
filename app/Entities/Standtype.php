<?php

declare(strict_types=1);

namespace App\Entities;

/**
 * Samenvatting van alle stands van één type (AA+, AA of A) op een evenement.
 */
final class Standtype
{
    /** Korte omschrijving per standtype, getoond op de standpagina. */
    private const OMSCHRIJVINGEN = [
        'AA+' => 'Beste positie bij de hoofdingang, extra vierkante meters en vermelding in de eventgids.',
        'AA'  => 'Ruime stand in de hoofdhal, op een druk bezocht looppad.',
        'A'   => 'Instapstand voor privéverkopers, ideaal om te ruilen en te verkopen.',
    ];

    public function __construct(
        public readonly int $evenementId,
        public readonly string $evenementNaam,
        public readonly string $standType,
        public readonly int $aantalStands,
        public readonly int $verhuurdeStands,
        public readonly int $vrijeStands,
        public readonly float $laagstePrijs,
        public readonly float $hoogstePrijs
    ) {
    }

    /**
     * Zet één databaserij om naar een Standtype-object.
     *
     * @param array<string, mixed> $rij
     */
    public static function vanRij(array $rij): self
    {
        return new self(
            (int) ($rij['evenement_id'] ?? 0),
            (string) ($rij['evenement_naam'] ?? ''),
            (string) ($rij['stand_type'] ?? ''),
            (int) ($rij['aantal_stands'] ?? 0),
            (int) ($rij['verhuurde_stands'] ?? 0),
            (int) ($rij['vrije_stands'] ?? 0),
            (float) ($rij['laagste_prijs'] ?? 0),
            (float) ($rij['hoogste_prijs'] ?? 0)
        );
    }

    public function omschrijving(): string
    {
        return self::OMSCHRIJVINGEN[$this->standType] ?? 'Standplaats op het evenement.';
    }

    public function isUitverkocht(): bool
    {
        return $this->vrijeStands <= 0;
    }

    /** Prijs voor twee dagen: de tweede dag kost de helft extra. */
    public function prijsTweeDagen(): float
    {
        return round($this->laagstePrijs * 1.5, 2);
    }
}
