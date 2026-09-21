<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Entities\Contactpersoon;

/**
 * Leest de contactpersonen van verkopers via de koppeltabel.
 */
final class ContactpersoonModel extends Model
{
    /**
     * Alle actieve contactpersonen van één verkoper.
     *
     * @return list<Contactpersoon>
     */
    public function haalPerVerkoper(int $verkoperId): array
    {
        $rijen = $this->roepProcedureAan('sp_contactpersoon_per_verkoper', [$verkoperId]);

        return array_map(
            static fn (array $rij): Contactpersoon => Contactpersoon::vanRij($rij),
            $rijen
        );
    }
}
