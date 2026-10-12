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
 * Verkopersoverzicht en het toevoegen van een nieuwe verkoper.
 *
 * GET  /verkopers          overzicht (read)
 * GET  /verkopers/nieuw    formulier om een verkoper toe te voegen (create)
 * POST /verkopers/nieuw    verwerkt dat formulier
 *
 * Happy flow  : de verkoper wordt opgeslagen met een automatisch gegenereerd
 *               verkopernummer en verschijnt in het overzicht.
 * Unhappy flow: ongeldige invoer (422), een databasefout (500) of geen
 *               (zoek)resultaat; de ingevulde gegevens blijven dan behouden.
 */
final class VerkoperController extends Controller
{
    /** Melding na een succesvol opgeslagen verkoper. */
    private const MELDING_TOEGEVOEGD = 'De verkoper is succesvol toegevoegd.';

    /** Melding wanneer het opslaan in de database mislukt. */
    private const MELDING_OPSLAAN_FOUT = 'Er is een fout opgetreden bij het opslaan van de verkoper. '
        . 'Probeer het later opnieuw.';

    /** Melding wanneer verplichte velden niet zijn ingevuld. */
    private const MELDING_VERPLICHT = 'Vul de gemarkeerde verplichte velden in.';

    /** Melding wanneer een veld een ongeldige waarde bevat. */
    private const MELDING_INVOER = 'Controleer de gemarkeerde velden.';

    /** Maximale veldlengtes, gelijk aan de kolombreedtes in de database. */
    private const MAX_NAAM = 120;

    private const MAX_EMAILADRES = 150;

    private const MAX_TELEFOONNUMMER = 25;

    /**
     * GET /verkopers
     * Overzicht van alle verkopers, gefilterd op soort en/of zoekterm.
     */
    public function index(Request $request): void
    {
        $soort    = $request->query('soort');
        $zoekterm = $request->query('zoek');

        $gegevens = [
            'titel'        => 'Verkopers',
            'pad'          => '/verkopers',
            'verkopers'    => [],
            'zoekterm'     => $zoekterm,
            'soort'        => $soort,
            'succesmelding' => $request->query('toegevoegd') !== '' ? self::MELDING_TOEGEVOEGD : null,
            'melding'      => null,
            'foutmelding'  => null,
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

    /**
     * GET /verkopers/nieuw
     * Toont het formulier waarmee een medewerker een verkoper toevoegt.
     */
    public function nieuw(Request $request): void
    {
        $this->toon('verkopers/nieuw', [
            'titel'       => 'Verkoper toevoegen',
            'pad'         => '/verkopers',
            'waarden'     => self::leegFormulier(),
            'foutmelding' => null,
            'fouten'      => [],
        ]);
    }

    /**
     * POST /verkopers/nieuw
     * Slaat de ingevulde verkoper op.
     *
     * Eerst de invoer controleren (ongeldige invoer -> 422), dan opslaan:
     * happy flow stuurt door naar het overzicht met een bevestiging, een
     * databasefout toont dezelfde pagina opnieuw met de foutmelding én de
     * ingevulde gegevens (500).
     */
    public function opslaan(Request $request): void
    {
        $waarden = [
            'naam'           => $request->post('naam'),
            'status'         => $request->post('status'),
            'emailadres'     => $request->post('emailadres'),
            'telefoonnummer' => $request->post('telefoonnummer'),
        ];

        $fouten = self::controleerInvoer($waarden);

        if ($fouten !== []) {
            $verplichtOntbreekt = $waarden['naam'] === ''
                || $waarden['emailadres'] === ''
                || $waarden['telefoonnummer'] === '';

            Logger::warning('Verkoper niet opgeslagen: de invoer is ongeldig', [
                'velden' => array_keys($fouten),
            ]);

            $this->toon('verkopers/nieuw', [
                'titel'       => 'Verkoper toevoegen',
                'pad'         => '/verkopers',
                'waarden'     => $waarden,
                'foutmelding' => $verplichtOntbreekt ? self::MELDING_VERPLICHT : self::MELDING_INVOER,
                'fouten'      => $fouten,
            ], 422);

            return;
        }

        try {
            $verkoperId = (new VerkoperModel())->voegToe(
                $waarden['naam'],
                $waarden['status'] === '1',
                $waarden['emailadres'],
                $waarden['telefoonnummer']
            );

            Logger::info('Verkoper toegevoegd', [
                'verkoper' => $verkoperId,
                'naam'     => $waarden['naam'],
                'status'   => $waarden['status'],
            ]);

            // Post/Redirect/Get: zo toont het overzicht de nieuwe verkoper
            // en is een verversing van de browser geen herverzending.
            header('Location: ' . url('/verkopers?toegevoegd=' . $verkoperId), true, 302);
            exit;
        } catch (DatabaseException $fout) {
            Logger::exception($fout, 'Verkoper kon niet worden opgeslagen', [
                'naam' => $waarden['naam'],
            ]);

            $this->toon('verkopers/nieuw', [
                'titel'       => 'Verkoper toevoegen',
                'pad'         => '/verkopers',
                'waarden'     => $waarden,
                'foutmelding' => self::MELDING_OPSLAAN_FOUT,
                'fouten'      => [],
            ], 500);
        }
    }

    /**
     * Controleert of de ingevulde velden aan de voorwaarden voldoen.
     *
     * Verplichte velden moeten gevuld zijn en de lengtes mogen de
     * kolombreedtes niet overschrijden; dan hoeft de database nooit een
     * afgekapte of halve waarde te ontvangen.
     *
     * @param  array<string, string> $waarden
     * @return array<string, string> Veldnaam => melding voor dat veld.
     */
    private static function controleerInvoer(array $waarden): array
    {
        $fouten = [];

        if ($waarden['naam'] === '') {
            $fouten['naam'] = 'Vul een naam in.';
        } elseif (mb_strlen($waarden['naam']) > self::MAX_NAAM) {
            $fouten['naam'] = 'De naam mag maximaal ' . self::MAX_NAAM . ' tekens bevatten.';
        }

        if ($waarden['emailadres'] === '') {
            $fouten['emailadres'] = 'Vul een e-mailadres in.';
        } elseif (mb_strlen($waarden['emailadres']) > self::MAX_EMAILADRES) {
            $fouten['emailadres'] = 'Het e-mailadres mag maximaal ' . self::MAX_EMAILADRES . ' tekens bevatten.';
        } elseif (filter_var($waarden['emailadres'], FILTER_VALIDATE_EMAIL) === false) {
            $fouten['emailadres'] = 'Vul een geldig e-mailadres in, bijvoorbeeld naam@voorbeeld.nl.';
        }

        if ($waarden['telefoonnummer'] === '') {
            $fouten['telefoonnummer'] = 'Vul een telefoonnummer in.';
        } elseif (mb_strlen($waarden['telefoonnummer']) > self::MAX_TELEFOONNUMMER) {
            $fouten['telefoonnummer'] = 'Het telefoonnummer mag maximaal '
                . self::MAX_TELEFOONNUMMER . ' tekens bevatten.';
        }

        if (!in_array($waarden['status'], ['0', '1'], true)) {
            $fouten['status'] = 'Kies een geldige status.';
        }

        return $fouten;
    }

    /**
     * Leeg formulier, gebruikt bij het eerste tonen van de pagina.
     *
     * @return array<string, string>
     */
    private static function leegFormulier(): array
    {
        return [
            'naam'           => '',
            'status'         => '0',
            'emailadres'     => '',
            'telefoonnummer' => '',
        ];
    }
}
