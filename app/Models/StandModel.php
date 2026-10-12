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
}
