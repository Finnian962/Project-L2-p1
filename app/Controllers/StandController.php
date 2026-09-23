<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DatabaseException;
use App\Core\Logger;
use App\Core\Request;
use App\Models\EvenementModel;
use App\Models\StandModel;
use App\Models\VerkoperModel;

/**
 * Toont het standaanbod voor verkopers: de standtypes AA+, AA en A met
 * prijzen, beschikbaarheid en de huurvoorwaarden voor één of twee dagen.
 */
final class StandController extends Controller
{
    use EvenementKeuze;

    /**
     * GET /stands?event={id}
     */
    public function index(Request $request): void
    {
        $gekozenEvenementId = $request->queryAlsGetal('event');

        $gegevens = [
            'titel'       => 'Stand huren',
            'pad'         => '/stands',
            'evenementen' => [],
            'evenement'   => null,
            'standtypes'  => [],
            'verkopers'   => [],
            'vrijeStands' => 0,
            'melding'     => null,
            'foutmelding' => null,
        ];

        try {
            $evenementen = (new EvenementModel())->haalOverzicht();
            $gegevens['evenementen'] = $evenementen;

            // Unhappy flow: zonder events kunnen er geen stands verhuurd worden.
            if ($evenementen === []) {
                $gegevens['melding'] = 'Er zijn momenteel geen stands beschikbaar.';
                Logger::warning('Standpagina geladen zonder events');

                $this->toon('stands/index', $gegevens);

                return;
            }

            $evenement = $this->kiesEvenement($evenementen, $gekozenEvenementId);

            // Unhappy flow: het opgegeven event bestaat niet.
            if ($evenement === null) {
                $gegevens['melding'] = 'Het gekozen event is niet gevonden. '
                    . 'Kies hieronder een van de geplande edities.';
                Logger::warning('Standpagina: onbekend event gekozen', ['event' => $gekozenEvenementId]);

                $this->toon('stands/index', $gegevens, 404);

                return;
            }

            $standModel = new StandModel();
            $standtypes = $standModel->haalStandtypes($evenement->id);

            $gegevens['evenement'] = $evenement;
            $gegevens['standtypes'] = $standtypes;
            $gegevens['vrijeStands'] = $standModel->telVrijeStands($standtypes);
            $gegevens['verkopers'] = (new VerkoperModel())->haalOverzicht($evenement->id);

            // Unhappy flow: de organisator heeft nog geen stands ingericht.
            if ($standtypes === []) {
                $gegevens['melding'] = 'Voor dit event zijn nog geen stands beschikbaar gesteld.';
                Logger::warning('Standpagina zonder stands', ['event' => $evenement->id]);
            }

            $this->toon('stands/index', $gegevens);
        } catch (DatabaseException $fout) {
            Logger::exception($fout, 'Standpagina kon de gegevens niet laden');

            $gegevens['foutmelding'] = $fout->getMessage();
            $this->toon('stands/index', $gegevens, 503);
        }
    }
}
