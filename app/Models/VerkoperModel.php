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
     * Slaat een nieuwe verkoper op met één contactpersoon.
     *
     * De opslag loopt via sp_verkoper_toevoegen: verkoper en contactpersoon
     * worden in één transactie weggeschreven. Wanneer dat mislukt gooit de
     * basisklasse een DatabaseException, zodat de controller de unhappy flow
     * kan tonen.
     *
     * @return int Het automatisch gegenereerde verkopernummer.
     *
     * @throws DatabaseException Wanneer het opslaan in de database faalt.
     */
    public function voegToe(
        string $naam,
        bool $specialeStatus,
        string $emailadres,
        string $telefoonnummer
    ): int {
        $rij = $this->roepProcedureAanVoorEenRij('sp_verkoper_toevoegen', [
            $naam,
            $specialeStatus ? 1 : 0,
            $emailadres,
            $telefoonnummer,
        ]);

        return (int) ($rij['verkoper_id'] ?? 0);
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
