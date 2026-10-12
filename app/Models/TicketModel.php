<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DatabaseException;
use App\Core\Model;

/**
 * Schrijft tickets en tijdsloten weg (tabel prijs en tabel ticket).
 *
 * De bestaande leestacties (beschikbaarheid, rapportage) tellen deze rijen
 * automatisch mee, zodat een nieuw tijdslot of een nieuw ticket direct
 * zichtbaar is op de ticketpagina.
 */
final class TicketModel extends Model
{
    /**
     * Maakt een nieuw tijdslot aan (tabel prijs) en geeft het tijdslotnummer
     * terug.
     *
     * Het tijdslot komt meteen op /tickets te staan met de beschikbaarheid van
     * de editie, zodat er tickets voor verkocht kunnen worden.
     *
     * @throws DatabaseException Wanneer het opslaan mislukt.
     */
    public function nieuwTijdslot(
        int $evenementId,
        string $datum,
        string $tijdslot,
        float $tarief,
        ?string $opmerking
    ): int {
        $rij = $this->roepProcedureAanVoorEenRij('sp_tijdslot_toevoegen', [
            $evenementId,
            $datum,
            $tijdslot,
            $tarief,
            $opmerking,
        ]);

        return (int) ($rij['tijdslotnummer'] ?? 0);
    }

    /**
     * Boekt een ticket op een bestaand tijdslot (tabel ticket) en geeft het
     * ticketnummer terug.
     *
     * Er hoeft geen bezoeker te zijn: die kolom blijft NULL wanneer een
     * medewerker het ticket toevoegt.
     *
     * @throws DatabaseException Wanneer het opslaan mislukt.
     */
    public function boekTicket(int $evenementId, int $prijsId, int $aantal): int
    {
        $rij = $this->roepProcedureAanVoorEenRij(
            'sp_ticket_toevoegen',
            [$evenementId, $prijsId, $aantal]
        );

        return (int) ($rij['ticketnummer'] ?? 0);
    }

    /**
     * Plaats een bestelling van een bezoeker: nieuwe (of bestaande) bezoeker
     * plus het ticket dat daaraan gekoppeld is.
     *
     * @return array{ticketnummer: int, bezoeker_id: int}
     *
     * @throws DatabaseException Wanneer de bestelling niet geplaatst kan worden.
     */
    public function bestel(
        int $evenementId,
        int $prijsId,
        int $aantal,
        string $naam,
        string $emailadres
    ): array {
        $rij = $this->roepProcedureAanVoorEenRij('sp_bestelling_plaatsen', [
            $evenementId,
            $prijsId,
            $aantal,
            $naam,
            $emailadres,
        ]);

        return [
            'ticketnummer' => (int) ($rij['ticketnummer'] ?? 0),
            'bezoeker_id'  => (int) ($rij['bezoeker_id'] ?? 0),
        ];
    }

    /**
     * Alle gegevens van een geplaatste bestelling voor de bevestiging.
     *
     * @return array<string, mixed>|null Null wanneer het ticket niet bestaat.
     *
     * @throws DatabaseException Wanneer de gegevens niet opgehaald kunnen worden.
     */
    public function haalBevestiging(int $ticketId): ?array
    {
        return $this->roepProcedureAanVoorEenRij('sp_bestelling_bevesting', [$ticketId]);
    }
}
