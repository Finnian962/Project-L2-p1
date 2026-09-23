<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DatabaseException;
use App\Core\Logger;
use App\Core\Request;
use App\Models\VerkoperModel;

/**
 * Toont het verkopersoverzicht: alle shops en privéverkopers met een stand.
 *
 * Happy flow  : er zijn verkopers gekoppeld aan een actief evenement.
 * Unhappy flow: geen verkopers, geen zoekresultaat of een falende database.
 */
final class VerkoperController extends Controller
{
    /**
     * GET /verkopers
     * Overzicht van alle verkopers, gesorteerd op soort en naam.
     */
    public function index(Request $request): void
    {
        $gegevens = [
            'titel'       => 'Verkopers',
            'pad'         => '/verkopers',
            'verkopers'   => [],
            'zoekterm'    => '',
            'soort'       => '',
            'melding'     => null,
            'foutmelding' => null,
        ];

        try {
            $gegevens['verkopers'] = (new VerkoperModel())->haalOverzicht();
        } catch (DatabaseException $fout) {
            Logger::exception($fout, 'Verkopersoverzicht kon niet worden geladen');
            $gegevens['foutmelding'] = $fout->getMessage();
        }

        $this->toon('verkopers/index', $gegevens, $gegevens['foutmelding'] === null ? 200 : 503);
    }
}
