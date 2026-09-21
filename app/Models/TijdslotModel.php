<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Entities\Tijdslot;

/**
 * Leest de tijdsloten (tabel prijs) met tarief en beschikbaarheid.
 */
final class TijdslotModel extends Model
{
    /**
     * Tijdsloten van één evenement, of van alle evenementen bij id 0.
     *
     * @return list<Tijdslot>
     */
    public function haalOverzicht(int $evenementId = 0): array
    {
        $rijen = $this->roepProcedureAan('sp_tijdslot_overzicht', [$evenementId]);

        return array_map(
            static fn (array $rij): Tijdslot => Tijdslot::vanRij($rij),
            $rijen
        );
    }

    /**
     * Tijdsloten van alle events, gegroepeerd per event.
     *
     * Hiermee toont het eventoverzicht per event het aantal beschikbare
     * tickets per tijdslot zonder per event een extra query te doen.
     *
     * @return array<int, list<Tijdslot>> Sleutel is het event-id.
     */
    public function haalGegroepeerdPerEvenement(): array
    {
        $gegroepeerd = [];

        foreach ($this->haalOverzicht(0) as $tijdslot) {
            $gegroepeerd[$tijdslot->evenementId][] = $tijdslot;
        }

        return $gegroepeerd;
    }

    /**
     * Tijdsloten gegroepeerd per dag, zodat de view per dag een blok kan tonen.
     *
     * @return array<string, list<Tijdslot>> Sleutel is de datum (Y-m-d).
     */
    public function haalGegroepeerdPerDag(int $evenementId = 0): array
    {
        $gegroepeerd = [];

        foreach ($this->haalOverzicht($evenementId) as $tijdslot) {
            $gegroepeerd[$tijdslot->datum][] = $tijdslot;
        }

        return $gegroepeerd;
    }
}
