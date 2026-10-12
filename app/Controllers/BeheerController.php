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
     * GET /beheer/stands/toevoegen
     * Formulier waarin de beheerder een nieuwe stand aanmaakt.
     */
    public function standToevoegen(Request $request): void
    {
        if (!$this->vereistRol('beheerder')) {
            return;
        }

        $gegevens = $this->haalFormulierGegevens($request->queryAlsGetal('event'));
        $gegevens['foutKop'] = $gegevens['databaseFout']
            ? 'Het formulier kon niet worden geladen'
            : 'Er kan geen stand worden toegevoegd';

        // Unhappy flow: database niet bereikbaar (503) of nog geen events (422).
        if ($gegevens['databaseFout']) {
            $this->toonToevoegen($gegevens, 503);

            return;
        }

        $this->toonToevoegen($gegevens, $gegevens['foutmelding'] === null ? 200 : 422);
    }

    /**
     * POST /beheer/stands/toevoegen
     * Slaat de nieuwe stand op en stuurt door naar het standenoverzicht.
     */
    public function standOpslaan(Request $request): void
    {
        if (!$this->vereistRol('beheerder')) {
            return;
        }

        $invoer = [
            'evenement_id' => $request->postAlsGetal('evenement_id'),
            'stand_type'   => $request->post('stand_type'),
            'prijs'        => $request->post('prijs'),
            'opmerking'    => $request->post('opmerking'),
        ];

        $gegevens = $this->haalFormulierGegevens($invoer['evenement_id']);
        $gegevens['invoer'] = $invoer;

        // Unhappy flow: de database is niet bereikbaar, dus de editie kon niet
        // geladen worden. Toon dát (503) en niet de invoercontrole, want die
        // zou anders ten onrechte melden dat er geen events zijn.
        if ($gegevens['databaseFout']) {
            $this->toonToevoegen($gegevens, 503);

            return;
        }

        // Unhappy flow: het formulier is (onvolledig) verkeerd ingevuld.
        $validatieFout = $this->controleerStandInvoer($invoer, $gegevens['evenementen']);

        if ($validatieFout !== null) {
            Logger::warning('Stand toevoegen: ongeldige invoer', ['reden' => $validatieFout]);

            $gegevens['foutmelding'] = $validatieFout;
            $this->toonToevoegen($gegevens, 422);

            return;
        }

        $evenement = null;
        foreach ($gegevens['evenementen'] as $optie) {
            if ($optie->id === $invoer['evenement_id']) {
                $evenement = $optie;
            }
        }

        $prijs = (float) str_replace(',', '.', $invoer['prijs']);

        try {
            $standId = (new StandModel())->voegToe(
                $invoer['evenement_id'],
                strtoupper($invoer['stand_type']),
                $prijs,
                $invoer['opmerking'] === '' ? null : $invoer['opmerking']
            );
        } catch (DatabaseException $fout) {
            // Unhappy flow: het evenement bestaat niet of de database ligt plat.
            Logger::exception($fout, 'Stand kon niet worden toegevoegd');

            $gegevens['foutmelding'] = $fout->getMessage();
            $this->toonToevoegen($gegevens, 503);

            return;
        }

        Logger::info('Stand toegevoegd', [
            'stand_id'     => $standId,
            'evenement'    => $evenement?->naam,
            'stand_type'   => $invoer['stand_type'],
            'prijs'        => $prijs,
            'toegevoegdDoor' => Auth::gebruikersnaam(),
        ]);

        Session::melding('De stand is succesvol toegevoegd.', 'info', 'Stand toegevoegd');

        $this->redirect('/beheer/stands?event=' . $invoer['evenement_id']);
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

    /**
     * De gegevens die het toevoegformulier nodig heeft: de evenementen om
     * uit te kiezen, de vorige invoer en een eventuele foutmelding.
     *
     * @return array<string, mixed>
     */
    private function haalFormulierGegevens(int $gekozenEvenementId): array
    {
        $gegevens = [
            'titel'          => 'Stand toevoegen',
            'pad'            => '/beheer',
            'evenementen'    => [],
            'evenementId'    => $gekozenEvenementId,
            'invoer'         => [
                'evenement_id' => $gekozenEvenementId,
                'stand_type'   => 'A',
                'prijs'        => '',
                'opmerking'    => '',
            ],
            'foutmelding'    => null,
            // Wordt true wanneer de evenementen niet geladen konden worden.
            'databaseFout'   => false,
        ];

        try {
            $evenementen = (new EvenementModel())->haalOverzicht();
            $gegevens['evenementen'] = $evenementen;

            // Zonder events kan er geen stand aangemaakt worden.
            if ($evenementen === []) {
                $gegevens['foutmelding'] = 'Er zijn nog geen events aangemaakt. '
                    . 'Maak eerst een evenement aan voordat je een stand toevoegt.';

                return $gegevens;
            }

            $evenement = $this->kiesEvenement($evenementen, $gekozenEvenementId);
            $gegevens['evenementId'] = $evenement?->id ?? 0;
            $gegevens['invoer']['evenement_id'] = $evenement?->id ?? 0;
        } catch (DatabaseException $fout) {
            Logger::exception($fout, 'Toevoegformulier kon de evenementen niet laden');
            $gegevens['foutmelding'] = $fout->getMessage();
            $gegevens['databaseFout'] = true;
        }

        return $gegevens;
    }

    /**
     * Controleert de ingevulde stand voordat hij opgeslagen wordt.
     *
     * @param array<string, mixed>              $invoer
     * @param list<\App\Entities\Evenement>     $evenementen
     *
     * @return string|null De foutmelding, of null wanneer de invoer klopt.
     */
    private function controleerStandInvoer(array $invoer, array $evenementen): ?string
    {
        if ($evenementen === []) {
            return 'Er zijn nog geen events aangemaakt. Maak eerst een evenement aan.';
        }

        $bestaandEvenement = false;
        foreach ($evenementen as $optie) {
            if ($optie->id === (int) $invoer['evenement_id']) {
                $bestaandEvenement = true;
            }
        }

        if (!$bestaandEvenement) {
            return 'Kies een geldige editie voor de stand.';
        }

        if (!in_array(strtoupper((string) $invoer['stand_type']), ['A', 'AA', 'AA+'], true)) {
            return 'Kies een geldig standtype: A, AA of AA+.';
        }

        $prijs = str_replace(',', '.', (string) $invoer['prijs']);

        if ($prijs === '' || !is_numeric($prijs) || (float) $prijs <= 0) {
            return 'Vul een geldige prijs in, bijvoorbeeld 495,00.';
        }

        if (mb_strlen((string) $invoer['opmerking']) > 255) {
            return 'De opmerking mag maximaal 255 tekens bevatten.';
        }

        return null;
    }

    /**
     * Toont het toevoegformulier met de eventuele foutmelding.
     *
     * @param array<string, mixed> $gegevens
     */
    private function toonToevoegen(array $gegevens, int $httpStatus): void
    {
        $this->toon('beheer/stand-toevoegen', $gegevens, $httpStatus);
    }
}
