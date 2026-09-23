<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DatabaseException;
use App\Core\Logger;
use App\Core\Request;
use App\Models\EvenementModel;
use App\Models\TijdslotModel;

/**
 * Toont de ticketpagina: alle toegangstijden met tarief en beschikbaarheid.
 *
 * Bezoekers zien per dag welke tijdsloten er zijn, wat een ticket kost en
 * hoeveel plaatsen er nog vrij zijn. Het daadwerkelijk kopen (create) staat
 * gepland voor Sprint 2.
 */
final class TicketController extends Controller
{
    use EvenementKeuze;

    /**
     * GET /tickets?event={id}
     */
    public function index(Request $request): void
    {
        $gekozenEvenementId = $request->queryAlsGetal('event');

        $gegevens = [
            'titel'        => 'Tickets',
            'pad'          => '/tickets',
            'evenementen'  => [],
            'evenement'    => null,
            'tijdsloten'   => [],
            'melding'      => null,
            'foutmelding'  => null,
        ];

        try {
            $evenementen = (new EvenementModel())->haalOverzicht();
            $gegevens['evenementen'] = $evenementen;

            // Unhappy flow: zonder events zijn er ook geen tickets.
            if ($evenementen === []) {
                $gegevens['melding'] = 'Er zijn nog geen events aangemaakt.';
                Logger::warning('Ticketpagina geladen zonder events');

                $this->toon('tickets/index', $gegevens);

                return;
            }

            $evenement = $this->kiesEvenement($evenementen, $gekozenEvenementId);

            // Unhappy flow: er is een event meegegeven dat niet bestaat.
            if ($evenement === null) {
                $gegevens['melding'] = 'Het gekozen event is niet gevonden. '
                    . 'Kies hieronder een van de geplande edities.';
                Logger::warning('Ticketpagina: onbekend event gekozen', ['event' => $gekozenEvenementId]);

                $this->toon('tickets/index', $gegevens, 404);

                return;
            }

            $gegevens['evenement'] = $evenement;
            $gegevens['tijdsloten'] = (new TijdslotModel())->haalGegroepeerdPerDag($evenement->id);

            // Unhappy flow: het event bestaat, maar heeft nog geen tijdsloten.
            if ($gegevens['tijdsloten'] === []) {
                $gegevens['melding'] = 'Er zijn momenteel geen tijdsloten beschikbaar.';
                Logger::warning('Ticketpagina zonder tijdsloten', ['event' => $evenement->id]);
            }

            $this->toon('tickets/index', $gegevens);
        } catch (DatabaseException $fout) {
            Logger::exception($fout, 'Ticketpagina kon de gegevens niet laden');

            $gegevens['foutmelding'] = $fout->getMessage();
            $this->toon('tickets/index', $gegevens, 503);
        }
    }
}
