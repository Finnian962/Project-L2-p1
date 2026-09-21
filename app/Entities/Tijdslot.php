<?php

declare(strict_types=1);

namespace App\Entities;

/**
 * Een toegangstijd (prijs) van een evenement met het bijbehorende tarief en
 * de actuele beschikbaarheid.
 */
final class Tijdslot
{
    public function __construct(
        public readonly int $prijsId,
        public readonly int $evenementId,
        public readonly string $evenementNaam,
        public readonly string $evenementLocatie,
        public readonly string $datum,
        public readonly string $tijdslot,
        public readonly float $tarief,
        public readonly ?string $opmerking,
        public readonly int $capaciteit,
        public readonly int $verkochteTickets,
        public readonly int $beschikbareTickets
    ) {
    }

    /**
     * Zet één databaserij om naar een Tijdslot-object.
     *
     * @param array<string, mixed> $rij
     */
    public static function vanRij(array $rij): self
    {
        return new self(
            (int) ($rij['prijs_id'] ?? 0),
            (int) ($rij['evenement_id'] ?? 0),
            (string) ($rij['evenement_naam'] ?? ''),
            (string) ($rij['evenement_locatie'] ?? ''),
            (string) ($rij['datum'] ?? ''),
            (string) ($rij['tijdslot'] ?? ''),
            (float) ($rij['tarief'] ?? 0),
            isset($rij['opmerking']) ? (string) $rij['opmerking'] : null,
            (int) ($rij['capaciteit'] ?? 0),
            (int) ($rij['verkochte_tickets'] ?? 0),
            (int) ($rij['beschikbare_tickets'] ?? 0)
        );
    }

    public function isUitverkocht(): bool
    {
        return $this->beschikbareTickets <= 0;
    }

    /** Bijna vol: minder dan een kwart van de plaatsen is nog vrij. */
    public function isBijnaVol(): bool
    {
        return !$this->isUitverkocht()
            && $this->capaciteit > 0
            && $this->beschikbareTickets <= (int) ceil($this->capaciteit * 0.25);
    }

    /** Percentage verkochte tickets van dit tijdslot. */
    public function bezettingsgraad(): int
    {
        if ($this->capaciteit <= 0) {
            return 0;
        }

        return (int) min(100, round($this->verkochteTickets / $this->capaciteit * 100));
    }
}
