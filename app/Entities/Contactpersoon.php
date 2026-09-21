<?php

declare(strict_types=1);

namespace App\Entities;

/**
 * Contactpersoon van een verkoper, waarop de organisator kan bereiken.
 */
final class Contactpersoon
{
    public function __construct(
        public readonly int $id,
        public readonly string $naam,
        public readonly string $telefoonnummer,
        public readonly string $emailadres,
        public readonly string $verkoperNaam
    ) {
    }

    /**
     * Zet één databaserij om naar een Contactpersoon-object.
     *
     * @param array<string, mixed> $rij
     */
    public static function vanRij(array $rij): self
    {
        return new self(
            (int) ($rij['id'] ?? 0),
            (string) ($rij['naam'] ?? ''),
            (string) ($rij['telefoonnummer'] ?? ''),
            (string) ($rij['emailadres'] ?? ''),
            (string) ($rij['verkoper_naam'] ?? '')
        );
    }

    /** Telefoonnummer zonder spaties, bruikbaar in een tel:-link. */
    public function telefoonLink(): string
    {
        return 'tel:' . str_replace(' ', '', $this->telefoonnummer);
    }
}
