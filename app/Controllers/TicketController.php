<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DatabaseException;
use App\Core\Logger;
use App\Core\Request;
use App\Models\EvenementModel;
use App\Models\TicketModel;
use App\Models\TijdslotModel;

/**
 * Beheert de ticketpagina's: het tijdslotenoverzicht voor bezoekers en de twee
 * schrijfacties voor een medewerker.
 *
 *  - /tickets/toevoegen  maakt een nieuw tijdslot aan. Dat tijdslot komt op
 *    de ticketpagina (/tickets) te staan, met de beschikbaarheid van de editie,
 *    zodat er meteen tickets voor verkocht kunnen worden.
 *  - /tickets/boeken     boekt een ticket op een bestaand tijdslot; de
 *    beschikbaarheid op /tickets gaat daarna direct omlaag.
 *  - /tickets/bestellen  laat een bezoeker tickets kopen: naam, e-mailadres
 *    en aantal, gevolgd door een (gesimuleerd) betaalscherm. Na een
 *    geslaagde betaling staat het ticket op naam van de bezoeker en is er
 *    een bevestiging; bij een mislukte betaling of technische fout wordt er
 *    niets geplaatst.
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

        // Terugkoppeling na een bestelling of een toegevoegd/gebekt ticket.
        $succesmelding = null;

        if ($request->heeftQuery('besteld')) {
            $succesmelding = 'Je bestelling is gelukt.';
        } elseif ($request->heeftQuery('toegevoegd')) {
            $succesmelding = 'Het ticket is succesvol toegevoegd.';
        }

        $gegevens = [
            'titel'         => 'Tickets',
            'pad'           => '/tickets',
            'evenementen'   => [],
            'evenement'     => null,
            'tijdsloten'    => [],
            'succesmelding' => $succesmelding,
            'bestelfout'    => $request->query('betaling') === 'mislukt'
                ? 'De betaling is niet gelukt. Er is geen ticket aan je bestelling toegewezen.'
                : null,
            'besteldTicket' => null,
            'melding'       => null,
            'foutmelding'   => null,
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

            // Bevestiging van de zojuist geplaatste bestelling: de e-mail is
            // verstuurd (vastgelegd in de technische log) en wordt ook getoond.
            $besteldTicketId = $request->queryAlsGetal('ticket');

            if ($succesmelding === 'Je bestelling is gelukt.' && $besteldTicketId > 0) {
                $gegevens['besteldTicket'] = (new TicketModel())->haalBevestiging($besteldTicketId);
            }

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

    /**
     * GET /tickets/toevoegen?event={id}
     *
     * Formulier waarmee een medewerker een nieuw tijdslot aanmaakt: editie,
     * dag, starttijd en tarief. Het nieuwe tijdslot komt daarna op de
     * ticketpagina (/tickets) te staan.
     */
    public function toevoegen(Request $request): void
    {
        $velden = [
            'evenement_id' => $request->queryAlsGetal('event'),
            'datum'        => '',
            'tijdslot'     => '',
            'tarief'       => '',
            'opmerking'    => '',
        ];

        try {
            $gegevens = $this->laadTijdslotGegevens($velden);
        } catch (DatabaseException $fout) {
            Logger::exception($fout, 'Ticketformulier kon de gegevens niet laden');

            $gegevens = $this->basisGegevens('Ticket toevoegen', '/tickets/toevoegen', $velden);
            $gegevens['foutmelding'] = $fout->getMessage();
            $gegevens['foutSoort'] = 'fout';
            $this->toon('tickets/toevoegen', $gegevens, 503);

            return;
        }

        $status = $this->pasEvenementMeldingenAan($gegevens);

        if ($request->heeftQuery('toegevoegd')) {
            $gegevens['succesmelding'] = 'Het ticket is succesvol toegevoegd.';
            Logger::info('Ticketformulier getoond na een geslaagde opslag');
        }

        $this->toon('tickets/toevoegen', $gegevens, $status);
    }

    /**
     * POST /tickets/toevoegen
     *
     * Maakt het nieuwe tijdslot aan en stuurt door naar de ticketpagina, waar
     * het nieuwe kaartje meteen te zien is.
     */
    public function opslaan(Request $request): void
    {
        $velden = [
            'evenement_id' => (int) $request->post('evenement_id'),
            'datum'        => trim($request->post('datum')),
            'tijdslot'     => trim($request->post('tijdslot')),
            'tarief'       => trim(str_replace(',', '.', $request->post('tarief'))),
            'opmerking'    => trim($request->post('opmerking')),
        ];

        // Standaardwaarden voor het geval het inlezen van de gegevens faalt.
        $gegevens = $this->basisGegevens('Ticket toevoegen', '/tickets/toevoegen', $velden);

        try {
            $gegevens = $this->laadTijdslotGegevens($velden);
            $validatie = $this->controleerNieuwTijdslot($gegevens, $velden);

            // Unhappy flow: het formulier klopt niet (meer).
            if ($validatie !== null) {
                $gegevens['foutmelding'] = $validatie;
                $this->toon('tickets/toevoegen', $gegevens, 422);

                return;
            }

            $evenementId = (int) $gegevens['velden']['evenement_id'];
            $tijdslot = $this->normaliseerTijdslot($velden['tijdslot']);
            $tijdslotnummer = (new TicketModel())->nieuwTijdslot(
                $evenementId,
                $velden['datum'],
                $tijdslot,
                (float) $velden['tarief'],
                $velden['opmerking'] === '' ? null : $velden['opmerking']
            );

            Logger::info('Nieuw tijdslot toegevoegd', [
                'tijdslotnummer' => $tijdslotnummer,
                'evenement'      => $evenementId,
                'datum'          => $velden['datum'],
                'tijdslot'       => $tijdslot,
                'tarief'         => $velden['tarief'],
            ]);

            // Redirect (PRG) naar de ticketpagina: daar staat het nieuwe
            // tijdslot direct tussen de andere kaartjes.
            header(
                'Location: ' . url('/tickets') . '?event=' . $evenementId . '&toegevoegd=1',
                true,
                303
            );
            exit;
        } catch (DatabaseException $fout) {
            // Unhappy flow: technische fout, het tijdslot is niet opgeslagen.
            Logger::exception($fout, 'Tijdslot kon niet worden opgeslagen');

            $gegevens['foutmelding'] = 'Het ticket kon niet worden opgeslagen. Probeer het later opnieuw.';
            $gegevens['foutSoort'] = 'fout';
            $this->toon('tickets/toevoegen', $gegevens, 503);
        }
    }

    /**
     * GET /tickets/boeken?event={id}&tijdslot={prijsId}
     *
     * Formulier waarmee een medewerker een ticket boekt op een bestaand
     * tijdslot: editie, tijdslot en aantal. Het overzicht op /tickets linkt
     * per tijdslot naar deze pagina, met dat tijdslot al geselecteerd.
     */
    public function boeken(Request $request): void
    {
        $velden = $this->standaardBoekVelden(
            $request->queryAlsGetal('event'),
            $request->queryAlsGetal('tijdslot')
        );

        try {
            // Bij een doorverwijzing vanaf het overzicht mag een onbekend
            // tijdslot terugvallen op het eerste tijdslot van de editie.
            $gegevens = $this->laadBoekGegevens($velden, true);
        } catch (DatabaseException $fout) {
            Logger::exception($fout, 'Boekformulier kon de gegevens niet laden');

            $gegevens = $this->basisGegevens('Ticket boeken', '/tickets/boeken', $velden);
            $gegevens['foutmelding'] = $fout->getMessage();
            $gegevens['foutSoort'] = 'fout';
            $this->toon('tickets/boeken', $gegevens, 503);

            return;
        }

        $status = $this->pasBoekMeldingenAan($gegevens);

        if ($request->heeftQuery('toegevoegd')) {
            $gegevens['succesmelding'] = 'Het ticket is succesvol toegevoegd.';
            Logger::info('Boekformulier getoond na een geslaagde opslag');
        }

        $this->toon('tickets/boeken', $gegevens, $status);
    }

    /**
     * POST /tickets/boeken
     *
     * Slaat het geboekte ticket op en stuurt door naar de ticketpagina, waar
     * de bijgewerkte beschikbaarheid direct te zien is.
     */
    public function boekOp(Request $request): void
    {
        $velden = [
            'evenement_id' => (int) $request->post('evenement_id'),
            'prijs_id'     => (int) $request->post('prijs_id'),
            'aantal'       => $this->postGetal($request->post('aantal')),
        ];

        // Standaardwaarden voor het geval het inlezen van de gegevens faalt.
        $gegevens = $this->basisGegevens('Ticket boeken', '/tickets/boeken', $velden);

        try {
            $gegevens = $this->laadBoekGegevens($velden);
            $validatie = $this->controleerBoeking($gegevens, $velden);

            // Unhappy flow: het formulier klopt niet (meer).
            if ($validatie !== null) {
                $gegevens['foutmelding'] = $validatie;
                $this->toon('tickets/boeken', $gegevens, 422);

                return;
            }

            $evenementId = (int) $gegevens['velden']['evenement_id'];
            $ticketnummer = (new TicketModel())->boekTicket(
                $evenementId,
                $velden['prijs_id'],
                $velden['aantal']
            );

            Logger::info('Ticket geboekt', [
                'ticketnummer' => $ticketnummer,
                'evenement'    => $evenementId,
                'tijdslot'     => $velden['prijs_id'],
                'aantal'       => $velden['aantal'],
            ]);

            // Redirect (PRG) naar de ticketpagina: daar is de nieuwe
            // beschikbaarheid meteen zichtbaar.
            header(
                'Location: ' . url('/tickets') . '?event=' . $evenementId . '&toegevoegd=1',
                true,
                303
            );
            exit;
        } catch (DatabaseException $fout) {
            // Unhappy flow: technische fout, het ticket is niet opgeslagen.
            Logger::exception($fout, 'Ticket kon niet worden opgeslagen');

            $gegevens['foutmelding'] = 'Het ticket kon niet worden opgeslagen. Probeer het later opnieuw.';
            $gegevens['foutSoort'] = 'fout';
            $this->toon('tickets/boeken', $gegevens, 503);
        }
    }

    // ---------------------------------------------------------------------
    // Tickets kopen (GET/POST /tickets/bestellen, POST /tickets/betalen)
    // ---------------------------------------------------------------------

    /**
     * GET /tickets/bestellen?event={id}&tijdslot={prijsId}
     *
     * Bestelformulier voor de bezoeker: hoeveel tickets, naam en e-mailadres.
     * Het overzicht op /tickets linkt per tijdslot naar deze pagina.
     */
    public function bestellen(Request $request): void
    {
        $velden = $this->standaardBestelVelden(
            $request->queryAlsGetal('event'),
            $request->queryAlsGetal('tijdslot')
        );

        try {
            // Bij een doorverwijzing vanaf het overzicht mag een onbekend
            // tijdslot terugvallen op het eerste tijdslot van de editie.
            $gegevens = $this->laadBoekGegevens($velden, true, 'Bestellen', '/tickets/bestellen');
        } catch (DatabaseException $fout) {
            Logger::exception($fout, 'Bestelformulier kon de gegevens niet laden');

            $gegevens = $this->basisGegevens('Bestellen', '/tickets/bestellen', $velden);
            $gegevens['foutmelding'] = $fout->getMessage();
            $gegevens['foutSoort'] = 'fout';
            $this->toon('tickets/bestellen', $gegevens, 503);

            return;
        }

        $status = $this->pasBoekMeldingenAan($gegevens);
        $this->toon('tickets/bestellen', $gegevens, $status);
    }

    /**
     * POST /tickets/bestellen
     *
     * Controleert de ingevulde bestelling en toont daarna het betaalscherm
     * (de gesimuleerde betaalprovider). Er wordt nog niets opgeslagen, dus
     * een mislukte betaling laat geen ticket achter.
     */
    public function controleerBestelling(Request $request): void
    {
        $velden = $this->bestelVeldenUitPost($request);

        // Standaardwaarden voor het geval het inlezen van de gegevens faalt.
        $gegevens = $this->basisGegevens('Bestellen', '/tickets/bestellen', $velden);

        try {
            $gegevens = $this->laadBoekGegevens($velden, false, 'Bestellen', '/tickets/bestellen');
            $validatie = $this->controleerBestelgegevens($gegevens, $velden);

            // Unhappy flow: het formulier klopt niet (meer).
            if ($validatie !== null) {
                $gegevens['foutmelding'] = $validatie;
                $this->toon('tickets/bestellen', $gegevens, 422);

                return;
            }

            // Alleen het betaalscherm tonen: opslaan gebeurt pas na de
            // betaling, zodat een afgekeurde betaling niets achterlaat.
            $gegevens['bestelling'] = $this->bouwBestelling($gegevens, $velden);
            $this->toon('tickets/betalen', $gegevens, 200);
        } catch (DatabaseException $fout) {
            // Unhappy flow: de gegevens konden niet gelezen worden.
            Logger::exception($fout, 'Bestelling kon niet worden gecontroleerd');

            $gegevens['foutmelding'] = 'Je bestelling is niet geplaatst. Probeer het later opnieuw.';
            $gegevens['foutSoort'] = 'fout';
            $this->toon('tickets/bestellen', $gegevens, 503);
        }
    }

    /**
     * POST /tickets/betalen
     *
     * Rondt de gesimuleerde betaling af. Bij "Betaling afronden" wordt de
     * bestelling opgeslagen (bezoeker + ticket) en de bevestiging verstuurd;
     * bij "Betaling mislukt" of een technische fout wordt er niets geplaatst
     * en krijgt de bezoeker geen ticket.
     */
    public function betalen(Request $request): void
    {
        $velden = $this->bestelVeldenUitPost($request);

        // Standaardwaarden voor het geval het inlezen van de gegevens faalt.
        $gegevens = $this->basisGegevens('Betalen', '/tickets/bestellen', $velden);

        // Unhappy flow: de betaalprovider keurt de betaling af. Er is nog
        // niets opgeslagen, dus er komt geen ticket.
        if ($velden['betaling'] !== 'geslaagd') {
            Logger::warning('Betaling mislukt, geen bestelling geplaatst', [
                'evenement' => $velden['evenement_id'],
                'tijdslot'  => $velden['prijs_id'],
                'aantal'    => $velden['aantal'],
                'reden'     => $velden['betaling'] === '' ? 'geen keuze' : $velden['betaling'],
            ]);

            header(
                'Location: ' . url('/tickets') . '?event=' . $velden['evenement_id'] . '&betaling=mislukt',
                true,
                303
            );
            exit;
        }

        try {
            // Opnieuw inladen: de beschikbaarheid kan tussen de twee
            // schermen in veranderd zijn.
            $gegevens = $this->laadBoekGegevens($velden, false, 'Betalen', '/tickets/bestellen');
            $validatie = $this->controleerBestelgegevens($gegevens, $velden);

            if ($validatie !== null) {
                // Unhappy flow: bijvoorbeeld net uitverkocht. Terug naar het
                // bestelformulier zodat de bezoeker een ander aantal kan
                // kiezen; er is niets opgeslagen.
                $gegevens['titel'] = 'Bestellen';
                $gegevens['foutmelding'] = $validatie;
                $this->toon('tickets/bestellen', $gegevens, 422);

                return;
            }

            $evenementId = (int) $gegevens['velden']['evenement_id'];
            $bestelling = $this->bouwBestelling($gegevens, $velden);

            $resultaat = (new TicketModel())->bestel(
                $evenementId,
                $velden['prijs_id'],
                $velden['aantal'],
                $velden['naam'],
                $velden['emailadres']
            );

            $ticketnummer = $resultaat['ticketnummer'];

            Logger::info('Bestelling geplaatst', [
                'ticketnummer' => $ticketnummer,
                'bezoeker'     => $resultaat['bezoeker_id'],
                'evenement'    => $evenementId,
                'tijdslot'     => $velden['prijs_id'],
                'aantal'       => $velden['aantal'],
                'totaal'       => euro($bestelling['totaal']),
            ]);

            // Er is geen mailserver in dit project: de bevestiging wordt
            // vastgelegd in de technische log én getoond op de ticketpagina.
            Logger::info('Bevestigingsmail verstuurd', [
                'aan'       => $velden['emailadres'],
                'onderwerp' => 'Je bestelling is gelukt',
                'ticket'    => $ticketnummer,
                'editie'    => $bestelling['editie'],
                'dag'       => datumNl($bestelling['datum']),
                'tijdslot'  => tijdKort($bestelling['tijdslot']),
                'aantal'    => $velden['aantal'],
                'totaal'    => euro($bestelling['totaal']),
            ]);

            // Redirect (PRG) naar de ticketpagina, met de melding uit de
            // user story én de bevestiging van de bestelling.
            header(
                'Location: ' . url('/tickets')
                    . '?event=' . $evenementId
                    . '&besteld=1&ticket=' . $ticketnummer,
                true,
                303
            );
            exit;
        } catch (DatabaseException $fout) {
            // Unhappy flow: technische fout (database of betaalprovider niet
            // bereikbaar). De bestelling is niet geplaatst en er is geen
            // ticket aan de bezoeker toegewezen.
            Logger::exception($fout, 'Bestelling kon niet worden geplaatst');

            $gegevens['bestelling'] = $this->bouwBestelling($gegevens, $velden);
            $gegevens['foutmelding'] = 'Je bestelling is niet geplaatst. Probeer het later opnieuw.';
            $gegevens['foutSoort'] = 'fout';
            $this->toon('tickets/betalen', $gegevens, 503);
        }
    }

    /**
     * Basisgegevens voor een formulier, zonder database-aanroepen.
     *
     * Handig als het inlezen van de gegevens zelf al mislukt.
     *
     * @param  array<string, mixed> $velden
     * @return array<string, mixed>
     */
    private function basisGegevens(string $titel, string $pad, array $velden): array
    {
        return [
            'titel'         => $titel,
            'pad'           => $pad,
            'evenementen'   => [],
            'evenement'     => null,
            'tijdsloten'    => [],
            'velden'        => $velden,
            'succesmelding' => null,
            'melding'       => null,
            'foutmelding'   => null,
            'foutSoort'     => 'waarschuwing',
        ];
    }

    // ---------------------------------------------------------------------
    // Nieuw tijdslot (GET/POST /tickets/toevoegen)
    // ---------------------------------------------------------------------

    /**
     * Ladt de evenementen, het gekozen event en de bestaande tijdsloten.
     *
     * @param  array<string, mixed> $velden
     * @return array<string, mixed>
     */
    private function laadTijdslotGegevens(array $velden): array
    {
        $gegevens = $this->basisGegevens('Ticket toevoegen', '/tickets/toevoegen', $velden);

        $gegevens['evenementen'] = (new EvenementModel())->haalOverzicht();
        $gegevens['evenement'] = $this->kiesEvenement($gegevens['evenementen'], (int) $velden['evenement_id']);
        $gegevens['tijdsloten'] = $gegevens['evenement'] === null
            ? []
            : (new TijdslotModel())->haalOverzicht($gegevens['evenement']->id);

        if ($gegevens['evenement'] !== null) {
            // Het gekozen event kan afwijken van de ingevulde waarde (0 of een
            // onbekend id): de view en de redirect moeten de echte waarde hebben.
            $gegevens['velden']['evenement_id'] = $gegevens['evenement']->id;
        }

        return $gegevens;
    }

    /**
     * Controleert editie, dag, starttijd en tarief en geeft de eerste fout
     * terug.
     *
     * De stored procedure voert dezelfde controles nog een keer uit.
     *
     * @param array<string, mixed> $gegevens
     * @param array<string, mixed> $velden
     */
    private function controleerNieuwTijdslot(array $gegevens, array $velden): ?string
    {
        if ($gegevens['evenement'] === null) {
            return 'Kies een editie.';
        }

        $evenement = $gegevens['evenement'];

        if (
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $velden['datum']) !== 1
            || strtotime($velden['datum']) === false
        ) {
            return 'Vul een datum in, bijvoorbeeld 14-11-2026.';
        }

        if ($this->tijdslotUrenMinuten($velden['tijdslot']) === null) {
            return 'Vul een starttijd in tussen 00:00 en 23:59, bijvoorbeeld 10:00.';
        }

        if ($velden['tarief'] === '' || !is_numeric($velden['tarief']) || (float) $velden['tarief'] <= 0) {
            return 'Vul een geldig tarief in, bijvoorbeeld 24.50.';
        }

        if ((float) $velden['tarief'] > 9999.99) {
            return 'Het tarief mag maximaal € 9.999,99 zijn.';
        }

        if (mb_strlen($velden['opmerking']) > 255) {
            return 'De opmerking mag maximaal 255 tekens bevatten.';
        }

        if ($velden['datum'] < $evenement->datum) {
            return 'De datum mag niet voor de start van de editie liggen (vanaf '
                . datumNl($evenement->datum) . ').';
        }

        $korteTijd = tijdKort($this->normaliseerTijdslot($velden['tijdslot']));

        foreach ($gegevens['tijdsloten'] as $tijdslot) {
            if ($tijdslot->datum === $velden['datum'] && tijdKort($tijdslot->tijdslot) === $korteTijd) {
                return 'Voor dit tijdstip bestaat al een tijdslot. Kies een andere dag of starttijd.';
            }
        }

        return null;
    }

    /**
     * Geeft uren en minuten terug van een ingevulde tijd (10:00 of 09:30:00),
     * of null wanneer de tijd niet klopt.
     *
     * @return array{int, int}|null
     */
    private function tijdslotUrenMinuten(string $tijd): ?array
    {
        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $tijd, $delen) !== 1) {
            return null;
        }

        $uren = (int) $delen[1];
        $minuten = (int) $delen[2];

        if ($uren > 23 || $minuten > 59) {
            return null;
        }

        return [$uren, $minuten];
    }

    /**
     * Zet een ingevulde tijd om naar 'HH:MM:SS' voor de database.
     */
    private function normaliseerTijdslot(string $tijd): string
    {
        $urenMinuten = $this->tijdslotUrenMinuten($tijd);

        if ($urenMinuten === null) {
            return $tijd;
        }

        return sprintf('%02d:%02d:00', $urenMinuten[0], $urenMinuten[1]);
    }

    // ---------------------------------------------------------------------
    // Ticket boeken (GET/POST /tickets/boeken)
    // ---------------------------------------------------------------------

    /**
     * De velden van het boekformulier met hun standaardwaarde.
     *
     * @return array{evenement_id: int, prijs_id: int, aantal: int}
     */
    private function standaardBoekVelden(int $evenementId, int $prijsId = 0): array
    {
        return [
            'evenement_id' => $evenementId,
            'prijs_id'     => $prijsId,
            'aantal'       => 1,
        ];
    }

    /**
     * De velden van het bestelformulier met hun standaardwaarde.
     *
     * @return array{evenement_id: int, prijs_id: int, aantal: int, naam: string, emailadres: string}
     */
    private function standaardBestelVelden(int $evenementId, int $prijsId = 0): array
    {
        return [
            'evenement_id' => $evenementId,
            'prijs_id'     => $prijsId,
            'aantal'       => 1,
            'naam'         => '',
            'emailadres'   => '',
        ];
    }

    /**
     * Leest de velden van het bestel- en betaalformulier uit het POST-verzoek.
     *
     * @return array{evenement_id: int, prijs_id: int, aantal: int, naam: string, emailadres: string, betaling?: string}
     */
    private function bestelVeldenUitPost(Request $request): array
    {
        return [
            'evenement_id' => (int) $request->post('evenement_id'),
            'prijs_id'     => (int) $request->post('prijs_id'),
            'aantal'       => $this->postGetal($request->post('aantal')),
            'naam'         => trim($request->post('naam')),
            'emailadres'   => trim($request->post('emailadres')),
            'betaling'     => $request->post('betaling'),
        ];
    }

    /**
     * Vult de evenementen, het gekozen event en de tijdsloten in.
     *
     * @param  array{evenement_id: int, prijs_id: int, aantal: int} $velden
     * @param  bool $gekozenTijdslotAanpassen  Wanneer true (de GET-route) valt
     *         een tijdslot dat niet bij de editie hoort terug op het eerste
     *         tijdslot; bij het opslaan blijft de ingevulde keuze staan, zodat
     *         de validatiemelding klopt.
     * @param  string $titel Paginatitel (de medewerker- en bezoekersformulieren
     *         hebben elk hun eigen kop).
     * @param  string $pad   Pad voor de menupijl, bijvoorbeeld /tickets/bestellen.
     * @return array<string, mixed>
     */
    private function laadBoekGegevens(
        array $velden,
        bool $gekozenTijdslotAanpassen = false,
        string $titel = 'Ticket boeken',
        string $pad = '/tickets/boeken'
    ): array {
        $gegevens = $this->basisGegevens($titel, $pad, $velden);

        $gegevens['evenementen'] = (new EvenementModel())->haalOverzicht();
        $gegevens['evenement'] = $this->kiesEvenement($gegevens['evenementen'], $velden['evenement_id']);
        $gegevens['tijdsloten'] = $gegevens['evenement'] === null
            ? []
            : (new TijdslotModel())->haalOverzicht($gegevens['evenement']->id);

        if ($gegevens['evenement'] !== null) {
            // Het gekozen event kan afwijken van de ingevulde waarde (0 of een
            // onbekend id): de view en de redirect moeten de echte waarde hebben.
            $gegevens['velden']['evenement_id'] = $gegevens['evenement']->id;
        }

        if (
            $gekozenTijdslotAanpassen
            && !$this->bevatTijdslot($gegevens['tijdsloten'], $gegevens['velden']['prijs_id'])
        ) {
            // Onbekend tijdslot (of eentje van een andere editie): het eerste
            // tijdslot van de gekozen editie nemen.
            $gegevens['velden']['prijs_id'] = $gegevens['tijdsloten'][0]->prijsId ?? 0;
        }

        // Zonder voorkeur het eerste tijdslot van de editie tonen.
        if ($gegevens['velden']['prijs_id'] === 0 && $gegevens['tijdsloten'] !== []) {
            $gegevens['velden']['prijs_id'] = $gegevens['tijdsloten'][0]->prijsId;
        }

        return $gegevens;
    }

    /**
     * Controleert editie, tijdslot en aantal en geeft de eerste fout terug.
     *
     * @param array<string, mixed>                         $gegevens
     * @param array{evenement_id: int, prijs_id: int, aantal: int} $velden
     */
    private function controleerBoeking(array $gegevens, array $velden): ?string
    {
        if ($gegevens['evenement'] === null) {
            return 'Kies een editie.';
        }

        if ($gegevens['tijdsloten'] === []) {
            return 'Er zijn momenteel geen tijdsloten beschikbaar voor deze editie.';
        }

        if ($velden['aantal'] < 1) {
            return 'Vul een geldig aantal in (minimaal 1).';
        }

        foreach ($gegevens['tijdsloten'] as $tijdslot) {
            if ($tijdslot->prijsId !== $velden['prijs_id']) {
                continue;
            }

            if ($tijdslot->beschikbareTickets <= 0) {
                return 'Dit tijdslot is uitverkocht. Kies een ander tijdslot.';
            }

            if ($velden['aantal'] > $tijdslot->beschikbareTickets) {
                return 'Er zijn nog maar ' . $tijdslot->beschikbareTickets
                    . ' tickets beschikbaar voor dit tijdslot.';
            }

            return null;
        }

        return 'Het gekozen tijdslot hoort niet bij deze editie.';
    }

    /**
     * Controleert de bestelling van een bezoeker en geeft de eerste fout
     * terug: naam, e-mailadres en daarna de gewone boekingcontroles.
     *
     * @param array<string, mixed> $gegevens
     * @param array<string, mixed> $velden
     */
    private function controleerBestelgegevens(array $gegevens, array $velden): ?string
    {
        $naam = (string) ($velden['naam'] ?? '');
        $emailadres = (string) ($velden['emailadres'] ?? '');

        if ($naam === '') {
            return 'Vul je naam in.';
        }

        if (mb_strlen($naam) > 100) {
            return 'Je naam mag maximaal 100 tekens bevatten.';
        }

        if ($emailadres === '') {
            return 'Vul je e-mailadres in.';
        }

        if (mb_strlen($emailadres) > 150) {
            return 'Het e-mailadres mag maximaal 150 tekens bevatten.';
        }

        if (filter_var($emailadres, FILTER_VALIDATE_EMAIL) === false) {
            return 'Vul een geldig e-mailadres in, bijvoorbeeld jan@voorbeeld.nl.';
        }

        return $this->controleerBoeking($gegevens, $velden);
    }

    /**
     * Zet de ingevulde bestelling en het gekozen tijdslot om naar de
     * samenvatting voor het betaalscherm (en de bevestiging).
     *
     * Wanneer het tijdslot niet gevonden wordt blijven de bedragen leeg: de
     * weergave toont dan alleen de melding.
     *
     * @param  array<string, mixed> $gegevens
     * @param  array<string, mixed> $velden
     * @return array<string, mixed>
     */
    private function bouwBestelling(array $gegevens, array $velden): array
    {
        $bestelling = [
            'evenement_id' => (int) ($gegevens['velden']['evenement_id'] ?? $velden['evenement_id'] ?? 0),
            'prijs_id'     => (int) ($velden['prijs_id'] ?? 0),
            'aantal'       => (int) ($velden['aantal'] ?? 0),
            'naam'         => (string) ($velden['naam'] ?? ''),
            'emailadres'   => (string) ($velden['emailadres'] ?? ''),
            'editie'       => $gegevens['evenement']?->naam ?? '',
            'datum'        => '',
            'tijdslot'     => '',
            'tarief'       => null,
            'totaal'       => null,
            'beschikbaar'  => null,
        ];

        foreach ($gegevens['tijdsloten'] ?? [] as $tijdslot) {
            if ($tijdslot->prijsId !== $bestelling['prijs_id']) {
                continue;
            }

            $bestelling['datum'] = $tijdslot->datum;
            $bestelling['tijdslot'] = $tijdslot->tijdslot;
            $bestelling['tarief'] = $tijdslot->tarief;
            $bestelling['totaal'] = $tijdslot->tarief * $bestelling['aantal'];
            $bestelling['beschikbaar'] = $tijdslot->beschikbareTickets;

            break;
        }

        return $bestelling;
    }

    /**
     * Geeft terug of het tijdslot in de lijst met tijdsloten van de editie zit.
     *
     * @param list<\App\Entities\Tijdslot> $tijdsloten
     */
    private function bevatTijdslot(array $tijdsloten, int $prijsId): bool
    {
        foreach ($tijdsloten as $tijdslot) {
            if ($tijdslot->prijsId === $prijsId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Meldingen die beide formulieren delen, plus de HTTP-status.
     *
     * @param array<string, mixed> $gegevens
     */
    private function pasEvenementMeldingenAan(array &$gegevens): int
    {
        if ($gegevens['evenementen'] === []) {
            $gegevens['melding'] = 'Er zijn nog geen events aangemaakt.';
            Logger::warning('Ticketformulier geopend zonder evenementen');

            return 200;
        }

        if ($gegevens['evenement'] === null) {
            $gegevens['melding'] = 'Het gekozen event is niet gevonden. '
                . 'Kies hieronder een van de geplande edities.';

            return 404;
        }

        return 200;
    }

    /**
     * Meldingen van het boekformulier, plus de HTTP-status.
     *
     * @param array<string, mixed> $gegevens
     */
    private function pasBoekMeldingenAan(array &$gegevens): int
    {
        $status = $this->pasEvenementMeldingenAan($gegevens);

        if ($gegevens['evenement'] !== null && $gegevens['tijdsloten'] === []) {
            $gegevens['melding'] = 'Er zijn momenteel geen tijdsloten beschikbaar.';
            Logger::warning('Boekformulier zonder tijdsloten', [
                'event' => $gegevens['evenement']->id,
            ]);
        }

        return $status;
    }

    /**
     * Leest een getal uit een formulierwaarde.
     *
     * Andere tekens worden 0, zodat de validatie een duidelijke melding geeft.
     */
    private function postGetal(string $waarde): int
    {
        return ctype_digit($waarde) ? (int) $waarde : 0;
    }
}
