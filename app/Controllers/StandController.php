<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DatabaseException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Session;
use App\Models\EvenementModel;
use App\Models\StandModel;
use App\Models\VerkoperModel;

/**
 * Toont het standaanbod voor verkopers: de standtypes AA+, AA en A met
 * prijzen, beschikbaarheid en de huurvoorwaarden voor één of twee dagen.
 *
 * Ingelogde standhouders kunnen hierbovenop een beschikbare stand reserveren
 * en hun eigen reserveringen terugzien.
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

    /**
     * GET /stands/beschikbaar?event={id}
     * De stands die een standhouder nog kan reserveren.
     */
    public function beschikbaar(Request $request): void
    {
        if (!$this->vereistRol('standhouder')) {
            return;
        }

        $gekozenEvenementId = $request->queryAlsGetal('event');

        $gegevens = [
            'titel'       => 'Beschikbare standen',
            'pad'         => '/stands',
            'evenementen' => [],
            'evenement'   => null,
            'standen'     => [],
            'melding'     => null,
            'foutmelding' => null,
        ];

        try {
            $evenementen = (new EvenementModel())->haalOverzicht();
            $gegevens['evenementen'] = $evenementen;

            // Unhappy flow: zonder events zijn er geen stands te reserveren.
            if ($evenementen === []) {
                $gegevens['melding'] = 'Er zijn momenteel geen stands beschikbaar.';
                Logger::warning('Beschikbare stands geladen zonder events');

                $this->toon('stands/beschikbaar', $gegevens);

                return;
            }

            $evenement = $this->kiesEvenement($evenementen, $gekozenEvenementId);

            // Unhappy flow: het opgegeven event bestaat niet.
            if ($evenement === null) {
                $gegevens['melding'] = 'Het gekozen event is niet gevonden. '
                    . 'Kies hieronder een van de geplande edities.';
                Logger::warning('Beschikbare stands: onbekend event gekozen', ['event' => $gekozenEvenementId]);

                $this->toon('stands/beschikbaar', $gegevens, 404);

                return;
            }

            $gegevens['evenement'] = $evenement;
            $gegevens['standen'] = (new StandModel())->haalBeschikbaar($evenement->id);

            // Unhappy flow: alles is al gereserveerd.
            if ($gegevens['standen'] === []) {
                $gegevens['melding'] = 'Er zijn geen vrije stands meer voor dit event. '
                    . 'Kies een andere editie.';
                Logger::warning('Beschikbare stands: niets vrij', ['event' => $evenement->id]);
            }

            $this->toon('stands/beschikbaar', $gegevens);
        } catch (DatabaseException $fout) {
            Logger::exception($fout, 'Beschikbare stands konden niet worden geladen');

            $gegevens['foutmelding'] = $fout->getMessage();
            $this->toon('stands/beschikbaar', $gegevens, 503);
        }
    }

    /**
     * POST /stands/reserveren
     * Slaat de reservering op en stuurt door naar 'Mijn reserveringen'.
     */
    public function reserveren(Request $request): void
    {
        if (!$this->vereistRol('standhouder')) {
            return;
        }

        $standId = $request->postAlsGetal('stand_id');
        $evenementId = $request->postAlsGetal('evenement_id');
        $terug = '/stands/beschikbaar' . ($evenementId > 0 ? '?event=' . $evenementId : '');

        // Unhappy flow: er is geen stand gekozen.
        if ($standId <= 0) {
            Session::melding(
                'Kies eerst een stand die je wilt reserveren.',
                'waarschuwing',
                'Geen stand gekozen'
            );

            $this->redirect($terug);
        }

        try {
            $resultaat = (new StandModel())->reserveer($standId, Auth::verkoperId());
        } catch (DatabaseException $fout) {
            Logger::exception($fout, 'Reservering kon niet worden opgeslagen');
            Logger::warning('Reservering mislukt', ['stand' => $standId]);

            Session::melding(
                'De reservering kon niet worden opgeslagen. Probeer het later opnieuw.',
                'fout',
                'Reservering mislukt'
            );

            $this->redirect($terug);
        }

        // Unhappy flow: de stand is intussen door iemand anders gereserveerd.
        if ($resultaat['aantal_gewijzigd'] !== 1) {
            Logger::warning('Reservering mislukt: stand niet meer vrij', ['stand' => $standId]);

            Session::melding(
                'Deze stand is net door iemand anders gereserveerd. Kies een andere stand.',
                'waarschuwing',
                'Niet meer beschikbaar'
            );

            $this->redirect($terug);
        }

        Logger::info('Stand gereserveerd', [
            'stand'         => $standId,
            'verkoper'      => Auth::verkoperId(),
            'gebruiker'     => Auth::gebruikersnaam(),
            'status'        => $resultaat['status'],
        ]);

        Session::melding('De stand is succesvol gereserveerd.', 'info', 'Stand gereserveerd');

        $this->redirect('/reserveringen');
    }

    /**
     * GET /reserveringen
     * De stands die de standhouder zelf gereserveerd heeft.
     */
    public function reserveringen(Request $request): void
    {
        if (!$this->vereistRol('standhouder')) {
            return;
        }

        $gegevens = [
            'titel'       => 'Mijn reserveringen',
            'pad'         => '/reserveringen',
            'standen'     => [],
            'melding'     => null,
            'foutmelding' => null,
        ];

        $verkoperId = Auth::verkoperId();

        // Unhappy flow: het account is niet aan een verkoper gekoppeld.
        if ($verkoperId <= 0) {
            $gegevens['melding'] = 'Dit account is niet gekoppeld aan een verkoper. '
                . 'Neem contact op met de organisator om een standhouderaccount te koppelen.';
            Logger::warning('Reserveringen opgevraagd zonder verkoper', ['gebruiker' => Auth::gebruikersnaam()]);

            $this->toon('stands/reserveringen', $gegevens);

            return;
        }

        try {
            $gegevens['standen'] = (new StandModel())->haalReserveringen($verkoperId);

            // Unhappy flow: nog niets gereserveerd.
            if ($gegevens['standen'] === []) {
                $gegevens['melding'] = 'Je hebt nog geen stands gereserveerd.';
                Logger::info('Geen reserveringen gevonden', ['verkoper' => $verkoperId]);
            }
        } catch (DatabaseException $fout) {
            Logger::exception($fout, 'Reserveringen konden niet worden geladen');

            $gegevens['foutmelding'] = $fout->getMessage();
            $this->toon('stands/reserveringen', $gegevens, 503);

            return;
        }

        $this->toon('stands/reserveringen', $gegevens);
    }
}
