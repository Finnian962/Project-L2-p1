<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DatabaseException;
use App\Core\Logger;
use App\Core\Request;
use App\Models\RapportModel;

/**
 * Beheeromgeving van de organisator.
 *
 * Sprint 1 toont hier de realtime rapportages (read) en de technische log.
 * Het beheren (create, update, delete) van events en stands volgt in Sprint 2.
 */
final class BeheerController extends Controller
{
    /**
     * GET /beheer
     * Realtime rapportage van ticketverkoop en standverhuur.
     */
    public function index(Request $request): void
    {
        $gegevens = [
            'titel'          => 'Beheer',
            'pad'            => '/beheer',
            'ticketverkoop'  => [],
            'standverhuur'   => [],
            'melding'        => null,
            'foutmelding'    => null,
        ];

        try {
            $rapportModel = new RapportModel();
            $gegevens['ticketverkoop'] = $rapportModel->haalTicketverkoop();
            $gegevens['standverhuur'] = $rapportModel->haalStandverhuur();

            // Unhappy flow: er valt nog niets te rapporteren.
            if ($gegevens['ticketverkoop'] === []) {
                $gegevens['melding'] = 'Er zijn nog geen events aangemaakt.';
                Logger::warning('Beheerrapport zonder events');
            }
        } catch (DatabaseException $fout) {
            Logger::exception($fout, 'Beheerrapport kon niet worden geladen');
            $gegevens['foutmelding'] = $fout->getMessage();
        }

        $this->toon('beheer/index', $gegevens, $gegevens['foutmelding'] === null ? 200 : 503);
    }

    /**
     * GET /beheer/log
     * Toont de laatste regels uit de technische log.
     */
    public function log(Request $request): void
    {
        $regels = Logger::laatsteRegels(150);

        $this->toon('beheer/log', [
            'titel'   => 'Technische log',
            'pad'     => '/beheer',
            'regels'  => $regels,
            'melding' => $regels === [] ? 'Er zijn vandaag nog geen logregels geschreven.' : null,
        ]);
    }
}
