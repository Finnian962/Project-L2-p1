<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DatabaseException;
use App\Core\Logger;
use App\Core\Request;
use App\Models\EvenementModel;
use App\Models\RapportModel;
use App\Models\StandModel;

/**
 * Beheeromgeving van de organisator.
 *
 * Alleen beschikbaar voor ingelogde beheerders (rol 'beheerder'); wie niet
 * ingelogd is, wordt doorgestuurd naar de inlogpagina. De omgeving toont de
 * realtime rapportages, het standenoverzicht en de technische log.
 */
final class BeheerController extends Controller
{
    use EvenementKeuze;

    /**
     * GET /beheer
     * Realtime rapportage van ticketverkoop en standverhuur.
     */
    public function index(Request $request): void
    {
        if (!$this->vereistRol('beheerder')) {
            return;
        }

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
     * GET /beheer/stands?event={id}
     * Overzicht van alle stands van een evenement met type, prijs, status
     * en verhuurder.
     */
    public function stands(Request $request): void
    {
        if (!$this->vereistRol('beheerder')) {
            return;
        }

        $gekozenEvenementId = $request->queryAlsGetal('event');

        $gegevens = [
            'titel'       => 'Stands beheren',
            'pad'         => '/beheer',
            'evenementen' => [],
            'evenement'   => null,
            'standen'     => [],
            'melding'     => null,
            'foutmelding' => null,
        ];

        try {
            $evenementen = (new EvenementModel())->haalOverzicht();
            $gegevens['evenementen'] = $evenementen;

            if ($evenementen === []) {
                // Unhappy flow: zonder events zijn er geen stands te tonen.
                $gegevens['melding'] = 'Er zijn nog geen events aangemaakt.';
                Logger::warning('Standenoverzicht geladen zonder events');

                $this->toon('beheer/stands', $gegevens);

                return;
            }

            $evenement = $this->kiesEvenement($evenementen, $gekozenEvenementId);

            if ($evenement === null) {
                $gegevens['melding'] = 'Het gekozen event is niet gevonden. '
                    . 'Kies hieronder een van de geplande edities.';
                Logger::warning('Standenoverzicht: onbekend event gekozen', ['event' => $gekozenEvenementId]);

                $this->toon('beheer/stands', $gegevens, 404);

                return;
            }

            $gegevens['evenement'] = $evenement;
            $gegevens['standen'] = (new StandModel())->haalOverzicht($evenement->id);

            if ($gegevens['standen'] === []) {
                $gegevens['melding'] = 'Voor dit event zijn nog geen stands aangemaakt.';
                Logger::warning('Standenoverzicht zonder stands', ['event' => $evenement->id]);
            }

            $this->toon('beheer/stands', $gegevens);
        } catch (DatabaseException $fout) {
            Logger::exception($fout, 'Standenoverzicht kon de gegevens niet laden');

            $gegevens['foutmelding'] = $fout->getMessage();
            $this->toon('beheer/stands', $gegevens, 503);
        }
    }

    /**
     * GET /beheer/log
     * Toont de laatste regels uit de technische log.
     */
    public function log(Request $request): void
    {
        if (!$this->vereistRol('beheerder')) {
            return;
        }

        $regels = Logger::laatsteRegels(150);

        $this->toon('beheer/log', [
            'titel'   => 'Technische log',
            'pad'     => '/beheer',
            'regels'  => $regels,
            'melding' => $regels === [] ? 'Er zijn vandaag nog geen logregels geschreven.' : null,
        ]);
    }
}
