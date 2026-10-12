<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Entities\Evenement;

/**
 * Leest evenementen uit de database (de M van MVC).
 *
 * Alle queries staan als stored procedure in de database; dit model roept
 * ze aan en vertaalt de rijen naar Evenement-objecten. Schrijven gaat via
 * sp_evenement_toevoegen; er gaat geen losse INSERT vanuit PHP naar MySQL.
 */
final class EvenementModel extends Model
{
    /**
     * Alle actieve evenementen, gesorteerd op datum van vroeg naar laat.
     *
     * @param  string $zoekterm Optioneel filter op naam of locatie.
     * @return list<Evenement>
     */
    public function haalOverzicht(string $zoekterm = ''): array
    {
        $rijen = $this->roepProcedureAan('sp_evenement_overzicht', [$zoekterm]);

        return array_map(
            static fn (array $rij): Evenement => Evenement::vanRij($rij),
            $rijen
        );
    }

    /**
     * Eén actief evenement op basis van het id.
     *
     * @return Evenement|null Null wanneer het evenement niet bestaat of inactief is.
     */
    public function haalDetails(int $evenementId): ?Evenement
    {
        $rij = $this->roepProcedureAanVoorEenRij('sp_evenement_details', [$evenementId]);

        return $rij === null ? null : Evenement::vanRij($rij);
    }

    /**
     * Het eerstvolgende evenement, gebruikt op de homepagina.
     */
    public function haalEerstvolgende(): ?Evenement
    {
        foreach ($this->haalOverzicht() as $evenement) {
            if ($evenement->isToekomstig()) {
                return $evenement;
            }
        }

        return null;
    }

    /**
     * Slaat een nieuw event op via sp_evenement_toevoegen.
     *
     * @return int Het id van het aangemaakte event.
     */
    public function voegToe(
        string $naam,
        string $datum,
        string $locatie,
        int $ticketsPerTijdslot,
        int $beschikbareStands,
        ?string $opmerking = null
    ): int {
        $rij = $this->roepProcedureAanVoorEenRij('sp_evenement_toevoegen', [
            $naam,
            $datum,
            $locatie,
            $ticketsPerTijdslot,
            $beschikbareStands,
            $opmerking,
        ]);

        return (int) ($rij['evenement_id'] ?? 0);
    }
}
