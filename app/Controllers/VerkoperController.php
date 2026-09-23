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
        $soort = $request->query('soort');

        $gegevens = [
            'titel'       => 'Verkopers',
            'pad'         => '/verkopers',
            'verkopers'   => [],
            'zoekterm'    => '',
            'soort'       => $soort,
            'melding'     => null,
            'foutmelding' => null,
        ];

        try {
            $verkopers = (new VerkoperModel())->haalOverzicht(0, $soort);
            $gegevens['verkopers'] = $verkopers;

            // Unhappy flow: nog geen verkopers (voor dit soort) op een actief evenement.
            if ($verkopers === []) {
                $gegevens['melding'] = $soort === ''
                    ? 'Er zijn nog geen verkopers bekend voor de komende editie.'
                    : 'Er zijn geen verkopers bekend die "' . $soort . '" verkopen.';
                Logger::warning('Verkopersoverzicht zonder resultaten', ['soort' => $soort]);
            }
        } catch (DatabaseException $fout) {
            Logger::exception($fout, 'Verkopersoverzicht kon niet worden geladen');
            $gegevens['foutmelding'] = $fout->getMessage();
        }

        $this->toon('verkopers/index', $gegevens, $gegevens['foutmelding'] === null ? 200 : 503);
    }
}
