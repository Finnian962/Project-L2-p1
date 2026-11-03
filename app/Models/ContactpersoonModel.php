<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Entities\Contactpersoon;

/**
 * Leest en wijzigt contactpersonen van verkopers via stored procedures.
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

    /**
     * Eén actieve contactpersoon, of null wanneer die niet bestaat.
     */
    public function haalDetails(int $contactpersoonId): ?Contactpersoon
    {
        $rij = $this->roepProcedureAanVoorEenRij('sp_contactpersoon_details', [$contactpersoonId]);

        return $rij === null ? null : Contactpersoon::vanRij($rij);
    }

    /**
     * Werkt de gegevens van een bestaande contactpersoon bij.
     *
     * @return Contactpersoon|null Null wanneer de persoon niet (meer) bestaat.
     */
    public function wijzig(
        int $contactpersoonId,
        string $naam,
        string $telefoonnummer,
        string $emailadres
    ): ?Contactpersoon {
        $rij = $this->roepProcedureAanVoorEenRij('sp_contactpersoon_wijzigen', [
            $contactpersoonId,
            $naam,
            $telefoonnummer,
            $emailadres,
        ]);

        return $rij === null ? null : Contactpersoon::vanRij($rij);
    }
}
