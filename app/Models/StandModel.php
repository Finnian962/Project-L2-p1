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
}
