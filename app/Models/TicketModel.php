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

    /**
     * Haalt een bestelling op met e-mailadres én ticketnummer.
     *
     * De bezoeker heeft geen account: die twee gegevens zijn samen de sleutel.
     * Een verkeerde combinatie geeft null terug, zodat niemand anders bij
     * andermans bestelling komt.
     *
     * De rij bevat onder meer `is_actief` (0 wanneer de bestelling al
     * geannuleerd is) en `mag_wijzigen` (1 zolang het evenement nog niet
     * begonnen is).
     *
     * @return array<string, mixed>|null Null wanneer de bestelling niet bestaat.
     *
     * @throws DatabaseException Wanneer de gegevens niet opgehaald kunnen worden.
     */
    public function haalBestelling(string $emailadres, int $ticketnummer): ?array
    {
        return $this->roepProcedureAanVoorEenRij('sp_bestelling_ophalen', [
            $emailadres,
            $ticketnummer,
        ]);
    }

    /**
     * Alle bestellingen van één e-mailadres, nieuwste eerst.
     *
     * Helpt de bezoeker het juiste ticketnummer te vinden wanneer e-mailadres
     * en ticketnummer niet bij elkaar horen.
     *
     * @return list<array<string, mixed>> Leeg wanneer het adres geen bestellingen heeft.
     *
     * @throws DatabaseException Wanneer de gegevens niet opgehaald kunnen worden.
     */
    public function haalBestellingenVanEmail(string $emailadres): array
    {
        return $this->roepProcedureAan('sp_bestelling_lijst', [$emailadres]);
    }

    /**
     * Wijzigt het aantal tickets van een bestelling en geeft het oude en het
     * nieuwe aantal plus het prijsverschil terug.
     *
     * @return array<string, mixed> Onder meer oud_aantal, nieuw_aantal,
     *                              prijsverschil, tarief en emailadres.
     *
     * @throws DatabaseException Wanneer de wijziging niet opgeslagen kan worden.
     */
    public function wijzigAantal(int $ticketnummer, string $emailadres, int $nieuwAantal): array
    {
        return $this->roepProcedureAanVoorEenRij('sp_bestelling_wijzigen', [
            $ticketnummer,
            $emailadres,
            $nieuwAantal,
        ]) ?? [];
    }

    /**
     * Annuleert een bestelling: de tickets worden ongeldig (is_actief = 0) en
     * het bedrag komt vrij.
     *
     * @return array<string, mixed> Onder meer aantal, tarief en het
     *                              terugbetaalde bedrag.
     *
     * @throws DatabaseException Wanneer de annulering niet opgeslagen kan worden.
     */
    public function annuleerBestelling(int $ticketnummer, string $emailadres): array
    {
        return $this->roepProcedureAanVoorEenRij('sp_bestelling_annuleren', [
            $ticketnummer,
            $emailadres,
        ]) ?? [];
    }
}
