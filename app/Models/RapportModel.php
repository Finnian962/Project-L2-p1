<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Leest de realtime rapportages voor de organisator.
 *
 * De rijen worden bewust als array teruggegeven: het zijn samengestelde
 * rapportregels en geen entiteiten uit het ERD.
 */
final class RapportModel extends Model
{
    /**
     * Verkochte tickets en omzet per evenement.
     *
     * @return list<array<string, mixed>>
     */
    public function haalTicketverkoop(): array
    {
        return $this->roepProcedureAan('sp_rapport_ticketverkoop');
    }

    /**
     * Verhuurde stands en omzet per evenement.
     *
     * @return list<array<string, mixed>>
     */
    public function haalStandverhuur(): array
    {
        return $this->roepProcedureAan('sp_rapport_standverhuur');
    }
}
