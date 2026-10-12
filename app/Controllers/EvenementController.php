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
use App\Models\TijdslotModel;
use App\Models\VerkoperModel;

/**
 * Toont het eventoverzicht en de detailpagina van een event.
 *
 * Dit is de read-functionaliteit van Sprint 1:
 *  - happy flow   : er zijn actieve events -> lijst op datum, vroeg naar laat;
 *  - unhappy flow : geen actieve events   -> melding "Er zijn nog geen events aangemaakt.";
 *  - unhappy flow : database niet bereikbaar of event bestaat niet.
 *
 * Event toevoegen (alleen beheerder):
 *  - happy flow   : verplichte velden correct -> opslaan en tonen in het overzicht;
 *  - unhappy flow : ontbrekende velden (422);
 *  - unhappy flow : ongeldige gegevens, bijvoorbeeld een ongeldige datum (422);
 *  - unhappy flow : database plat of niet bereikbaar (503).
 */
final class EvenementController extends Controller
{
    /** Melding wanneer de organisator nog geen enkel event heeft aangemaakt. */
    private const MELDING_GEEN_EVENTS = 'Er zijn nog geen events aangemaakt.';

    /** Melding na een succesvol opgeslagen event. */
    private const MELDING_TOEGEVOEGD = 'Het event is succesvol toegevoegd.';

    /** Melding wanneer verplichte velden niet zijn ingevuld. */
    private const MELDING_VERPLICHT = 'Vul de gemarkeerde verplichte velden in.';

    /** Melding wanneer een veld een ongeldige waarde bevat. */
    private const MELDING_INVOER = 'Controleer de gemarkeerde velden. Er is een ongeldige waarde ingevuld.';

    private const MAX_NAAM = 150;

    private const MAX_LOCATIE = 150;

    private const MAX_OPMERKING = 255;

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
                'succesmelding'      => $request->queryAlsGetal('toegevoegd') > 0 ? self::MELDING_TOEGEVOEGD : null,
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
                'succesmelding'      => null,
                'melding'            => null,
                'foutmelding'        => self::MELDING_STORING,
            ], 503);
        }
    }

    /**
     * GET /beheer/events/toevoegen
     * Formulier om als beheerder een nieuw event aan te maken.
     */
    public function nieuw(Request $request): void
    {
        if (!$this->vereistRol('beheerder')) {
            return;
        }

        try {
            // Unhappy flow: database plat of niet bereikbaar bij het openen.
            (new EvenementModel())->haalOverzicht();
        } catch (DatabaseException $fout) {
            Logger::exception($fout, 'Toevoegformulier event kon niet worden geladen');

            $this->toonFormulier(
                self::leegFormulier(),
                self::MELDING_STORING,
                [],
                503,
                'Het formulier kon niet worden geladen'
            );

            return;
        }

        $this->toonFormulier(self::leegFormulier(), null, []);
    }

    /**
     * POST /beheer/events/toevoegen
     * Slaat het ingevulde event op.
     *
     * Ontbrekende of ongeldige velden -> 422, niets wegschrijven.
     * Happy flow stuurt door naar het eventoverzicht (Post/Redirect/Get).
     */
    public function opslaan(Request $request): void
    {
        if (!$this->vereistRol('beheerder')) {
            return;
        }

        $waarden = [
            'naam'                        => $request->post('naam'),
            'datum'                       => $request->post('datum'),
            'locatie'                     => $request->post('locatie'),
            'aantal_tickets_per_tijdslot' => $request->post('aantal_tickets_per_tijdslot'),
            'beschikbare_stands'          => $request->post('beschikbare_stands'),
            'opmerking'                   => $request->post('opmerking'),
        ];

        $fouten = self::controleerInvoer($waarden);

        if ($fouten !== []) {
            $verplichtOntbreekt = $waarden['naam'] === ''
                || $waarden['datum'] === ''
                || $waarden['locatie'] === ''
                || $waarden['aantal_tickets_per_tijdslot'] === ''
                || $waarden['beschikbare_stands'] === '';

            Logger::warning('Event niet opgeslagen: de invoer is ongeldig', [
                'velden' => array_keys($fouten),
            ]);

            $this->toonFormulier(
                $waarden,
                $verplichtOntbreekt ? self::MELDING_VERPLICHT : self::MELDING_INVOER,
                $fouten,
                422
            );

            return;
        }

        try {
            $evenementId = (new EvenementModel())->voegToe(
                $waarden['naam'],
                $waarden['datum'],
                $waarden['locatie'],
                (int) $waarden['aantal_tickets_per_tijdslot'],
                (int) $waarden['beschikbare_stands'],
                $waarden['opmerking'] === '' ? null : $waarden['opmerking']
            );

            Logger::info('Event toegevoegd', [
                'evenement'    => $evenementId,
                'naam'         => $waarden['naam'],
                'toegevoegdDoor' => Auth::gebruikersnaam(),
            ]);

            Session::melding(self::MELDING_TOEGEVOEGD, 'info', 'Event toegevoegd');

            $this->redirect('/events?toegevoegd=' . $evenementId);
        } catch (DatabaseException $fout) {
            // Unhappy flow: database plat, verkeerde databasenaam of query mislukt.
            Logger::exception($fout, 'Event kon niet worden opgeslagen', [
                'naam' => $waarden['naam'],
            ]);

            $this->toonFormulier(
                $waarden,
                self::MELDING_STORING,
                [],
                503,
                'Het event is niet opgeslagen'
            );
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
     * Toont het toevoegformulier, met eventuele fouten en de vorige invoer.
     *
     * @param array<string, string> $waarden
     * @param array<string, string> $fouten
     */
    private function toonFormulier(
        array $waarden,
        ?string $foutmelding,
        array $fouten,
        int $httpStatus = 200,
        string $foutKop = 'Het event is niet opgeslagen'
    ): void {
        $this->toon('beheer/event-toevoegen', [
            'titel'       => 'Event toevoegen',
            'pad'         => '/beheer',
            'waarden'     => $waarden,
            'foutmelding' => $foutmelding,
            'foutKop'     => $foutKop,
            'fouten'      => $fouten,
        ], $httpStatus);
    }

    /**
     * Controleert verplichte velden en of datum en aantallen geldig zijn.
     *
     * @param  array<string, string> $waarden
     * @return array<string, string> Veldnaam => melding.
     */
    private static function controleerInvoer(array $waarden): array
    {
        $fouten = [];

        if ($waarden['naam'] === '') {
            $fouten['naam'] = 'Vul een naam in.';
        } elseif (mb_strlen($waarden['naam']) > self::MAX_NAAM) {
            $fouten['naam'] = 'De naam mag maximaal ' . self::MAX_NAAM . ' tekens bevatten.';
        }

        if ($waarden['datum'] === '') {
            $fouten['datum'] = 'Vul een datum in.';
        } elseif (!self::isGeldigeDatum($waarden['datum'])) {
            $fouten['datum'] = 'Vul een geldige datum in, bijvoorbeeld 2026-11-14.';
        }

        if ($waarden['locatie'] === '') {
            $fouten['locatie'] = 'Vul een locatie in.';
        } elseif (mb_strlen($waarden['locatie']) > self::MAX_LOCATIE) {
            $fouten['locatie'] = 'De locatie mag maximaal ' . self::MAX_LOCATIE . ' tekens bevatten.';
        }

        if ($waarden['aantal_tickets_per_tijdslot'] === '') {
            $fouten['aantal_tickets_per_tijdslot'] = 'Vul het aantal tickets per tijdslot in.';
        } elseif (!self::isGeheelGetal($waarden['aantal_tickets_per_tijdslot'])) {
            $fouten['aantal_tickets_per_tijdslot'] = 'Vul een geldig aantal tickets in, bijvoorbeeld 250.';
        }

        if ($waarden['beschikbare_stands'] === '') {
            $fouten['beschikbare_stands'] = 'Vul het aantal beschikbare stands in.';
        } elseif (!self::isGeheelGetal($waarden['beschikbare_stands'])) {
            $fouten['beschikbare_stands'] = 'Vul een geldig aantal stands in, bijvoorbeeld 30.';
        }

        if (mb_strlen($waarden['opmerking']) > self::MAX_OPMERKING) {
            $fouten['opmerking'] = 'De opmerking mag maximaal ' . self::MAX_OPMERKING . ' tekens bevatten.';
        }

        return $fouten;
    }

    /** True bij een bestaande kalenderdatum in het formaat jjjj-mm-dd. */
    private static function isGeldigeDatum(string $waarde): bool
    {
        $datum = \DateTimeImmutable::createFromFormat('Y-m-d', $waarde);

        return $datum !== false && $datum->format('Y-m-d') === $waarde;
    }

    /** True bij een niet-negatief geheel getal (0 is toegestaan). */
    private static function isGeheelGetal(string $waarde): bool
    {
        return preg_match('/^\d+$/', $waarde) === 1 && (int) $waarde <= 100000;
    }

    /**
     * Leeg formulier, gebruikt bij het eerste tonen van de pagina.
     *
     * @return array<string, string>
     */
    private static function leegFormulier(): array
    {
        return [
            'naam'                        => '',
            'datum'                       => '',
            'locatie'                     => '',
            'aantal_tickets_per_tijdslot' => '',
            'beschikbare_stands'          => '',
            'opmerking'                   => '',
        ];
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
