<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DatabaseException;
use App\Core\Logger;
use App\Core\Request;
use App\Entities\Verkoper;
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
     * Overzicht van alle verkopers, gefilterd op soort en/of zoekterm.
     */
    public function index(Request $request): void
    {
        $soort    = $request->query('soort');
        $zoekterm = $request->query('zoek');

        $gegevens = [
            'titel'       => 'Verkopers',
            'pad'         => '/verkopers',
            'verkopers'   => [],
            'zoekterm'    => $zoekterm,
            'soort'       => $soort,
            'melding'     => null,
            'foutmelding' => null,
        ];

        try {
            $verkopers = (new VerkoperModel())->haalOverzicht(0, $soort);

            // De procedure filtert op soort; op naam filteren we zelf.
            if ($zoekterm !== '') {
                $verkopers = array_values(array_filter(
                    $verkopers,
                    static fn (Verkoper $verkoper): bool => mb_stripos($verkoper->naam, $zoekterm) !== false
                ));
            }

            $gegevens['verkopers'] = $verkopers;

            // Unhappy flow: de lijst is leeg na filteren.
            if ($verkopers === []) {
                if ($zoekterm !== '') {
                    $gegevens['melding'] = 'Er zijn geen verkopers gevonden voor "' . $zoekterm
                        . '". Probeer een andere zoekopdracht.';
                } elseif ($soort !== '') {
                    $gegevens['melding'] = 'Er zijn geen verkopers bekend die "' . $soort . '" verkopen.';
                } else {
                    $gegevens['melding'] = 'Er zijn nog geen verkopers bekend voor de komende editie.';
                }

                Logger::warning('Verkopersoverzicht zonder resultaten', [
                    'soort'    => $soort,
                    'zoekterm' => $zoekterm,
                ]);
            }
        } catch (DatabaseException $fout) {
            Logger::exception($fout, 'Verkopersoverzicht kon niet worden geladen');
            $gegevens['foutmelding'] = $fout->getMessage();
        }

        $this->toon('verkopers/index', $gegevens, $gegevens['foutmelding'] === null ? 200 : 503);
    }
}
