<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Entities\Verkoper;

/**
 * Leest verkopers, partners en side-stands uit de database.
 */
final class VerkoperModel extends Model
{
    /**
     * Alle verkopers met een stand op een actief evenement.
     *
     * @param  string $soort Optioneel filter, bijvoorbeeld 'Sneakers'.
     * @return list<Verkoper>
     */
    public function haalOverzicht(int $evenementId = 0, string $soort = ''): array
    {
        return $this->naarVerkopers(
            $this->roepProcedureAan('sp_verkoper_overzicht', [$evenementId, $soort])
        );
    }

    /**
     * Partners: verkopers met een speciale status, logo en extra informatie.
     *
     * @return list<Verkoper>
     */
    public function haalPartners(int $evenementId = 0): array
    {
        return $this->naarVerkopers(
            $this->roepProcedureAan('sp_partner_overzicht', [$evenementId])
        );
    }

    /**
     * Side-stands: eten en drinken, kids corner, customizers, tattoo,
     * barbershop en DJ-sets.
     *
     * @return list<Verkoper>
     */
    public function haalSideStands(int $evenementId = 0): array
    {
        return $this->naarVerkopers(
            $this->roepProcedureAan('sp_sidestand_overzicht', [$evenementId])
        );
    }

    /**
     * Groepeert side-stands op soort, zodat de view per categorie kan tonen.
     *
     * @return array<string, list<Verkoper>>
     */
    public function haalSideStandsPerSoort(int $evenementId = 0): array
    {
        $gegroepeerd = [];

        foreach ($this->haalSideStands($evenementId) as $verkoper) {
            $gegroepeerd[$verkoper->verkooptSoort][] = $verkoper;
        }

        return $gegroepeerd;
    }

    /**
     * @param  list<array<string, mixed>> $rijen
     * @return list<Verkoper>
     */
    private function naarVerkopers(array $rijen): array
    {
        return array_map(
            static fn (array $rij): Verkoper => Verkoper::vanRij($rij),
            $rijen
        );
    }
}
