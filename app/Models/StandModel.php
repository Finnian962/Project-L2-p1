<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Entities\Standtype;

/**
 * Leest de standtypes (AA+, AA, A) met prijzen en beschikbaarheid.
 */
final class StandModel extends Model
{
    /**
     * Standtypes van één evenement, of van alle evenementen bij id 0.
     *
     * @return list<Standtype>
     */
    public function haalStandtypes(int $evenementId = 0): array
    {
        $rijen = $this->roepProcedureAan('sp_standtype_overzicht', [$evenementId]);

        return array_map(
            static fn (array $rij): Standtype => Standtype::vanRij($rij),
            $rijen
        );
    }

    /**
     * Telt het aantal vrije stands over alle opgehaalde standtypes heen.
     *
     * @param list<Standtype> $standtypes
     */
    public function telVrijeStands(array $standtypes): int
    {
        return array_sum(array_map(
            static fn (Standtype $standtype): int => $standtype->vrijeStands,
            $standtypes
        ));
    }

    /**
     * Alle stands van een evenement (of van alle evenementen bij id 0) met
     * verhuurder en status, voor het beheeroverzicht.
     *
     * @return list<array<string, mixed>>
     */
    public function haalOverzicht(int $evenementId = 0): array
    {
        return $this->roepProcedureAan('sp_stand_overzicht', [$evenementId]);
    }

    /**
     * Voegt een nieuwe, vrije stand toe aan een evenement.
     *
     * @return int Het id van de aangemaakte stand.
     *
     * @throws DatabaseException Wanneer het evenement niet bestaat of het
     *                           standtype niet is toegestaan.
     */
    public function voegToe(int $evenementId, string $standType, float $prijs, ?string $opmerking = null): int
    {
        $rij = $this->roepProcedureAanVoorEenRij('sp_stand_toevoegen', [
            $evenementId,
            $standType,
            $prijs,
            $opmerking,
        ]);

        return (int) ($rij['stand_id'] ?? 0);
    }

    /**
     * De stands die nog vrij zijn voor een evenement (0 = alle evenementen).
     *
     * @return list<array<string, mixed>>
     */
    public function haalBeschikbaar(int $evenementId = 0): array
    {
        return $this->roepProcedureAan('sp_stand_beschikbaar', [$evenementId]);
    }

    /**
     * Reserveert een stand voor een standhouder.
     *
     * De procedure controleert zelf of de stand nog vrij is: twee
     * reserveringen van dezelfde stand kunnen daardoor niet botsen.
     *
     * @return array{aantal_gewijzigd: int, status: string}
     *
     * @throws DatabaseException Wanneer de stand of verkoper niet bestaat.
     */
    public function reserveer(int $standId, int $verkoperId): array
    {
        $rij = $this->roepProcedureAanVoorEenRij('sp_stand_reserveren', [$standId, $verkoperId]) ?? [];

        return [
            'aantal_gewijzigd' => (int) ($rij['aantal_gewijzigd'] ?? 0),
            'status'           => (string) ($rij['status'] ?? ''),
        ];
    }

    /**
     * Alle gereserveerde stands van één verkoper (de standhouder).
     *
     * @return list<array<string, mixed>>
     */
    public function haalReserveringen(int $verkoperId): array
    {
        if ($verkoperId <= 0) {
            return [];
        }

        return $this->roepProcedureAan('sp_reservering_overzicht', [$verkoperId]);
    }
}
