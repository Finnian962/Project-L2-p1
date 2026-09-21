<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DatabaseException;
use App\Core\Logger;
use App\Core\Request;
use App\Models\EvenementModel;
use App\Models\StandModel;
use App\Models\TijdslotModel;
use App\Models\VerkoperModel;

/**
 * Toont het eventoverzicht en de detailpagina van een event.
 *
 * Dit is de read-functionaliteit van Sprint 1:
 *  - happy flow   : er zijn actieve events -> lijst op datum, vroeg naar laat;
 *  - unhappy flow : geen actieve events   -> melding "Er zijn nog geen events aangemaakt.";
 *  - unhappy flow : database niet bereikbaar of event bestaat niet.
 */
final class EvenementController extends Controller
{
    /** Melding wanneer de organisator nog geen enkel event heeft aangemaakt. */
    private const MELDING_GEEN_EVENTS = 'Er zijn nog geen events aangemaakt.';

    /**
     * Melding bij een storing: verkeerde databasenaam, database plat,
     * of een eventlink die niet (meer) bestaat.
     */
    private const MELDING_STORING = DatabaseException::GEBRUIKERSMELDING;

    /**
     * GET /events
     * Overzicht van alle actieve events, gesorteerd op datum.
     */
    public function index(Request $request): void
    {
        $zoekterm = $request->query('zoek');

        try {
            $evenementen = (new EvenementModel())->haalOverzicht($zoekterm);

            // Per event de tijdsloten, zodat het overzicht het aantal
            // beschikbare tickets per tijdslot kan tonen.
            $tijdslotenPerEvent = $evenementen === []
                ? []
                : (new TijdslotModel())->haalGegroepeerdPerEvenement();

            // Unhappy flow: de query is gelukt, maar levert geen rijen op.
            $melding = null;
            if ($evenementen === []) {
                $melding = $zoekterm === ''
                    ? self::MELDING_GEEN_EVENTS
                    : 'Er zijn geen events gevonden voor "' . $zoekterm . '". Probeer een andere zoekopdracht.';

                Logger::warning('Eventoverzicht zonder resultaten', ['zoekterm' => $zoekterm]);
            }

            $this->toon('evenementen/index', [
                'titel'              => 'Events',
                'pad'                => '/events',
                'evenementen'        => $evenementen,
                'tijdslotenPerEvent' => $tijdslotenPerEvent,
                'zoekterm'           => $zoekterm,
                'melding'            => $melding,
                'foutmelding'        => null,
            ]);
        } catch (DatabaseException $fout) {
            // Unhappy flow: database plat, verkeerde databasenaam of query mislukt.
            Logger::exception($fout, 'Eventoverzicht kon niet worden geladen');

            $this->toon('evenementen/index', [
                'titel'              => 'Events',
                'pad'                => '/events',
                'evenementen'        => [],
                'tijdslotenPerEvent' => [],
                'zoekterm'           => $zoekterm,
                'melding'            => null,
                'foutmelding'        => self::MELDING_STORING,
            ], 503);
        }
    }

    /**
     * GET /events/{id}
     * Detailpagina van één event met tijdsloten, stands en verkopers.
     */
    public function detail(Request $request, string $id): void
    {
        // Unhappy flow: een id dat geen getal is, bestaat sowieso niet.
        if (!ctype_digit($id)) {
            Logger::warning('Ongeldig event-id opgevraagd', ['id' => $id]);
            $this->toonNietGevonden();

            return;
        }

        $evenementId = (int) $id;

        try {
            $evenement = (new EvenementModel())->haalDetails($evenementId);

            // Unhappy flow: het event bestaat niet of is op inactief gezet.
            if ($evenement === null) {
                Logger::warning('Event niet gevonden', ['id' => $evenementId]);
                $this->toonNietGevonden();

                return;
            }

            $this->toon('evenementen/detail', [
                'titel'       => $evenement->naam,
                'pad'         => '/events',
                'evenement'   => $evenement,
                'tijdsloten'  => (new TijdslotModel())->haalGegroepeerdPerDag($evenementId),
                'standtypes'  => (new StandModel())->haalStandtypes($evenementId),
                'verkopers'   => (new VerkoperModel())->haalOverzicht($evenementId, 'Sneakers'),
                'sideStands'  => (new VerkoperModel())->haalSideStandsPerSoort($evenementId),
                'foutmelding' => null,
            ]);
        } catch (DatabaseException $fout) {
            // Unhappy flow: database plat of verkeerde databasenaam bij het openen van een event.
            Logger::exception($fout, 'Eventdetails konden niet worden geladen');

            $this->toon('fouten/melding', [
                'titel'       => 'Onze excuses',
                'pad'         => '/events',
                'kop'         => 'Onze excuses',
                'foutmelding' => self::MELDING_STORING,
            ], 503);
        }
    }

    /**
     * Unhappy flow: de eventlink bestaat niet of het event is inactief.
     */
    private function toonNietGevonden(): void
    {
        $this->toon('fouten/melding', [
            'titel'       => 'Onze excuses',
            'pad'         => '/events',
            'kop'         => 'Onze excuses',
            'foutmelding' => self::MELDING_STORING,
        ], 404);
    }
}
