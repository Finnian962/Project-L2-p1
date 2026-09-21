<?php

declare(strict_types=1);

namespace App\Entities;

/**
 * Eén editie van Sneakerness, inclusief de afgeleide ticket- en standgegevens
 * die de stored procedure meelevert.
 */
final class Evenement
{
    public function __construct(
        public readonly int $id,
        public readonly string $naam,
        public readonly string $datum,
        public readonly string $locatie,
        public readonly int $aantalTicketsPerTijdslot,
        public readonly int $beschikbareStands,
        public readonly ?string $opmerking,
        public readonly ?string $eersteDag,
        public readonly ?string $laatsteDag,
        public readonly int $aantalDagen,
        public readonly int $aantalTijdsloten,
        public readonly ?float $laagsteTarief,
        public readonly ?float $hoogsteTarief,
        public readonly int $totaleCapaciteit,
        public readonly int $verkochteTickets,
        public readonly int $beschikbareTickets,
        public readonly int $verhuurdeStands,
        public readonly int $vrijeStands
    ) {
    }

    /**
     * Zet één databaserij om naar een Evenement-object.
     *
     * @param array<string, mixed> $rij
     */
    public static function vanRij(array $rij): self
    {
        return new self(
            (int) ($rij['id'] ?? 0),
            (string) ($rij['naam'] ?? ''),
            (string) ($rij['datum'] ?? ''),
            (string) ($rij['locatie'] ?? ''),
            (int) ($rij['aantal_tickets_per_tijdslot'] ?? 0),
            (int) ($rij['beschikbare_stands'] ?? 0),
            isset($rij['opmerking']) ? (string) $rij['opmerking'] : null,
            isset($rij['eerste_dag']) ? (string) $rij['eerste_dag'] : null,
            isset($rij['laatste_dag']) ? (string) $rij['laatste_dag'] : null,
            (int) ($rij['aantal_dagen'] ?? 0),
            (int) ($rij['aantal_tijdsloten'] ?? 0),
            isset($rij['laagste_tarief']) ? (float) $rij['laagste_tarief'] : null,
            isset($rij['hoogste_tarief']) ? (float) $rij['hoogste_tarief'] : null,
            (int) ($rij['totale_capaciteit'] ?? 0),
            (int) ($rij['verkochte_tickets'] ?? 0),
            (int) ($rij['beschikbare_tickets'] ?? 0),
            (int) ($rij['verhuurde_stands'] ?? 0),
            (int) ($rij['vrije_stands'] ?? 0)
        );
    }

    /** Startdatum van het evenement (valt terug op het veld datum). */
    public function startDatum(): string
    {
        return $this->eersteDag ?? $this->datum;
    }

    /** Einddatum van het evenement (tweedaags evenement heeft een tweede dag). */
    public function eindDatum(): string
    {
        return $this->laatsteDag ?? $this->datum;
    }

    /** Alle tickets van dit evenement zijn verkocht. */
    public function isUitverkocht(): bool
    {
        return $this->totaleCapaciteit > 0 && $this->beschikbareTickets <= 0;
    }

    /** Er zijn nog geen tijdsloten ingepland door de organisator. */
    public function heeftTijdsloten(): bool
    {
        return $this->aantalTijdsloten > 0;
    }

    /** Percentage verkochte tickets, afgerond op hele procenten. */
    public function bezettingsgraad(): int
    {
        if ($this->totaleCapaciteit <= 0) {
            return 0;
        }

        return (int) min(100, round($this->verkochteTickets / $this->totaleCapaciteit * 100));
    }

    /** Ligt het evenement nog in de toekomst? */
    public function isToekomstig(): bool
    {
        return strtotime($this->eindDatum()) >= strtotime(date('Y-m-d'));
    }

    /** Stad uit het locatieveld, bijvoorbeeld 'Rotterdam'. */
    public function stad(): string
    {
        $delen = explode(',', $this->locatie);

        return trim(end($delen));
    }
}
