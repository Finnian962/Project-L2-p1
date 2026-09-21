<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Entities\Evenement;

/**
 * Hergebruikte logica voor pagina's met een eventkiezer (tickets en stands).
 */
trait EvenementKeuze
{
    /**
     * Kiest het gevraagde event uit de lijst. Zonder keuze wordt het
     * eerstvolgende toekomstige event gekozen.
     *
     * @param  list<Evenement> $evenementen
     * @return Evenement|null  Null wanneer het gevraagde id niet voorkomt.
     */
    private function kiesEvenement(array $evenementen, int $gekozenEvenementId): ?Evenement
    {
        if ($gekozenEvenementId > 0) {
            foreach ($evenementen as $evenement) {
                if ($evenement->id === $gekozenEvenementId) {
                    return $evenement;
                }
            }

            return null;
        }

        foreach ($evenementen as $evenement) {
            if ($evenement->isToekomstig()) {
                return $evenement;
            }
        }

        return $evenementen[0] ?? null;
    }
}
