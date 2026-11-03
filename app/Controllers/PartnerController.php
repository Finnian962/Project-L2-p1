<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DatabaseException;
use App\Core\Logger;
use App\Core\Request;
use App\Entities\Contactpersoon;
use App\Models\ContactpersoonModel;
use App\Models\VerkoperModel;

/**
 * Toont partners (met logo en extra informatie) en de side-stands:
 * eten en drinken, kids corner, customizers, tattoo, barbershop en DJ-sets.
 *
 * Contactpersoon wijzigen (alleen beheerder):
 *  - happy flow   : bestaande gegevens wijzigen → opslaan en tonen in de lijst;
 *  - unhappy flow : verplicht veld leeg (422);
 *  - unhappy flow : ongeldige gegevens, bijvoorbeeld een ongeldig e-mailadres (422);
 *  - unhappy flow : database plat of niet bereikbaar (503).
 */
final class PartnerController extends Controller
{
    /** Melding na een succesvol gewijzigde contactpersoon. */
    private const MELDING_GEWIJZIGD = 'De contactpersoon is succesvol gewijzigd.';

    /** Melding wanneer verplichte velden niet zijn ingevuld. */
    private const MELDING_VERPLICHT = 'Vul de gemarkeerde verplichte velden in.';

    /** Melding wanneer een veld een ongeldige waarde bevat. */
    private const MELDING_INVOER = 'Controleer de gemarkeerde velden. Er is een ongeldige waarde ingevuld.';

    private const MELDING_STORING = DatabaseException::GEBRUIKERSMELDING;

    private const MAX_NAAM = 100;

    private const MAX_TELEFOONNUMMER = 25;

    private const MAX_EMAILADRES = 150;
    /**
     * GET /partners
     */
    public function index(Request $request): void
    {
        $gegevens = [
            'titel'       => 'Partners',
            'pad'         => '/partners',
            'partners'    => [],
            'melding'     => null,
            'foutmelding' => null,
        ];

        try {
            $partners = (new VerkoperModel())->haalPartners();
            $gegevens['partners'] = $partners;

            // Unhappy flow: nog geen partners gekoppeld aan een event.
            if ($partners === []) {
                $gegevens['melding'] = 'Er zijn nog geen partners bekend voor de komende editie.';
                Logger::warning('Partneroverzicht zonder resultaten');
            }
        } catch (DatabaseException $fout) {
            Logger::exception($fout, 'Partneroverzicht kon niet worden geladen');
            $gegevens['foutmelding'] = $fout->getMessage();
        }

        $this->toon('partners/index', $gegevens, $gegevens['foutmelding'] === null ? 200 : 503);
    }

    /**
     * GET /side-stands
     */
    public function sideStands(Request $request): void
    {
        $gegevens = [
            'titel'       => 'Side-stands',
            'pad'         => '/side-stands',
            'perSoort'    => [],
            'melding'     => null,
            'foutmelding' => null,
        ];

        try {
            $perSoort = (new VerkoperModel())->haalSideStandsPerSoort();
            $gegevens['perSoort'] = $perSoort;

            // Unhappy flow: nog geen side-stands ingedeeld.
            if ($perSoort === []) {
                $gegevens['melding'] = 'Er zijn nog geen side-stands bekend voor de komende editie.';
                Logger::warning('Side-standoverzicht zonder resultaten');
            }
        } catch (DatabaseException $fout) {
            Logger::exception($fout, 'Side-standoverzicht kon niet worden geladen');
            $gegevens['foutmelding'] = $fout->getMessage();
        }

        $this->toon('partners/side-stands', $gegevens, $gegevens['foutmelding'] === null ? 200 : 503);
    }

    /**
     * GET /verkopers/{id}/contact
     * Contactpersonen van een verkoper (voor de organisator).
     */
    public function contactpersonen(Request $request, string $id): void
    {
        // Unhappy flow: id is geen getal.
        if (!ctype_digit($id)) {
            Logger::warning('Ongeldig verkoper-id opgevraagd', ['id' => $id]);

            $this->toon('fouten/melding', [
                'titel'       => 'Verkoper niet gevonden',
                'pad'         => '/partners',
                'kop'         => 'Deze verkoper bestaat niet',
                'foutmelding' => 'Het verkopernummer "' . $id . '" is ongeldig.',
            ], 404);

            return;
        }

        $gegevens = [
            'titel'           => 'Contactpersonen',
            'pad'             => '/partners',
            'verkoperId'      => (int) $id,
            'contactpersonen' => [],
            'succesmelding'   => $request->heeftQuery('gewijzigd') ? self::MELDING_GEWIJZIGD : null,
            'melding'         => null,
            'foutmelding'     => null,
        ];

        try {
            $contactpersonen = (new ContactpersoonModel())->haalPerVerkoper((int) $id);
            $gegevens['contactpersonen'] = $contactpersonen;

            // Unhappy flow: verkoper zonder (actieve) contactpersonen.
            if ($contactpersonen === []) {
                $gegevens['melding'] = 'Er zijn nog geen contactpersonen vastgelegd voor deze verkoper.';
                Logger::warning('Verkoper zonder contactpersonen', ['verkoper' => $id]);
            }
        } catch (DatabaseException $fout) {
            Logger::exception($fout, 'Contactpersonen konden niet worden geladen');
            $gegevens['foutmelding'] = $fout->getMessage();
        }

        $this->toon('partners/contact', $gegevens, $gegevens['foutmelding'] === null ? 200 : 503);
    }

    /**
     * GET /verkopers/{id}/contact/{contactId}/wijzigen
     * Formulier om als beheerder een bestaande contactpersoon te wijzigen.
     */
    public function wijzigen(Request $request, string $id, string $contactId): void
    {
        if (!$this->vereistRol('beheerder')) {
            return;
        }

        if (!$this->zijnGeldigeIds($id, $contactId)) {
            $this->toonContactNietGevonden($id);

            return;
        }

        try {
            $contactpersoon = $this->haalContactVanVerkoper((int) $id, (int) $contactId);
        } catch (DatabaseException $fout) {
            Logger::exception($fout, 'Wijzigformulier contactpersoon kon niet worden geladen');
            $this->toonWijzigFormulier(
                (int) $id,
                (int) $contactId,
                self::leegFormulier(),
                self::MELDING_STORING,
                [],
                503,
                'Het formulier kon niet worden geladen',
                ''
            );

            return;
        }

        if ($contactpersoon === null) {
            $this->toonContactNietGevonden($id);

            return;
        }

        $this->toonWijzigFormulier(
            $contactpersoon->verkoperId,
            $contactpersoon->id,
            [
                'naam'           => $contactpersoon->naam,
                'telefoonnummer' => $contactpersoon->telefoonnummer,
                'emailadres'     => $contactpersoon->emailadres,
            ],
            null,
            [],
            200,
            '',
            $contactpersoon->verkoperNaam
        );
    }

    /**
     * POST /verkopers/{id}/contact/{contactId}/wijzigen
     * Slaat de gewijzigde contactgegevens op.
     *
     * Ontbrekende of ongeldige velden -> 422, niets wegschrijven.
     * Happy flow stuurt door naar de contactlijst (Post/Redirect/Get).
     */
    public function opslaanWijziging(Request $request, string $id, string $contactId): void
    {
        if (!$this->vereistRol('beheerder')) {
            return;
        }

        if (!$this->zijnGeldigeIds($id, $contactId)) {
            $this->toonContactNietGevonden($id);

            return;
        }

        $verkoperId = (int) $id;
        $contactpersoonId = (int) $contactId;
        $waarden = [
            'naam'           => $request->post('naam'),
            'telefoonnummer' => $request->post('telefoonnummer'),
            'emailadres'     => $request->post('emailadres'),
        ];

        $fouten = self::controleerInvoer($waarden);

        if ($fouten !== []) {
            $verplichtOntbreekt = $waarden['naam'] === ''
                || $waarden['telefoonnummer'] === ''
                || $waarden['emailadres'] === '';

            Logger::warning('Contactpersoon niet gewijzigd: de invoer is ongeldig', [
                'verkoper'        => $verkoperId,
                'contactpersoon'  => $contactpersoonId,
                'velden'          => array_keys($fouten),
            ]);

            $this->toonWijzigFormulier(
                $verkoperId,
                $contactpersoonId,
                $waarden,
                $verplichtOntbreekt ? self::MELDING_VERPLICHT : self::MELDING_INVOER,
                $fouten,
                422,
                'De contactpersoon is niet opgeslagen',
                $this->haalVerkoperNaam($verkoperId, $contactpersoonId)
            );

            return;
        }

        try {
            $bestaand = $this->haalContactVanVerkoper($verkoperId, $contactpersoonId);

            if ($bestaand === null) {
                $this->toonContactNietGevonden((string) $verkoperId);

                return;
            }

            $gewijzigd = (new ContactpersoonModel())->wijzig(
                $contactpersoonId,
                $waarden['naam'],
                $waarden['telefoonnummer'],
                $waarden['emailadres']
            );

            if ($gewijzigd === null) {
                $this->toonContactNietGevonden((string) $verkoperId);

                return;
            }

            Logger::info('Contactpersoon gewijzigd', [
                'verkoper'       => $verkoperId,
                'contactpersoon' => $contactpersoonId,
                'naam'           => $waarden['naam'],
                'gewijzigdDoor'  => Auth::gebruikersnaam(),
            ]);

            $this->redirect('/verkopers/' . $verkoperId . '/contact?gewijzigd=1');
        } catch (DatabaseException $fout) {
            Logger::exception($fout, 'Contactpersoon kon niet worden gewijzigd', [
                'verkoper'       => $verkoperId,
                'contactpersoon' => $contactpersoonId,
            ]);

            $this->toonWijzigFormulier(
                $verkoperId,
                $contactpersoonId,
                $waarden,
                self::MELDING_STORING,
                [],
                503,
                'De contactpersoon is niet opgeslagen',
                $this->haalVerkoperNaam($verkoperId, $contactpersoonId)
            );
        }
    }

    /**
     * @param array<string, string> $waarden
     * @param array<string, string> $fouten
     */
    private function toonWijzigFormulier(
        int $verkoperId,
        int $contactpersoonId,
        array $waarden,
        ?string $foutmelding,
        array $fouten,
        int $httpStatus = 200,
        string $foutKop = 'De contactpersoon is niet opgeslagen',
        string $verkoperNaam = ''
    ): void {
        $this->toon('partners/contact-wijzigen', [
            'titel'            => 'Contactpersoon wijzigen',
            'pad'              => '/partners',
            'verkoperId'       => $verkoperId,
            'contactpersoonId' => $contactpersoonId,
            'verkoperNaam'     => $verkoperNaam,
            'waarden'          => $waarden,
            'foutmelding'      => $foutmelding,
            'foutKop'          => $foutKop,
            'fouten'           => $fouten,
        ], $httpStatus);
    }

    /**
     * Controleert verplichte velden en of e-mailadres en telefoonnummer geldig zijn.
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

        if ($waarden['telefoonnummer'] === '') {
            $fouten['telefoonnummer'] = 'Vul een telefoonnummer in.';
        } elseif (mb_strlen($waarden['telefoonnummer']) > self::MAX_TELEFOONNUMMER) {
            $fouten['telefoonnummer'] = 'Het telefoonnummer mag maximaal '
                . self::MAX_TELEFOONNUMMER . ' tekens bevatten.';
        } elseif (!self::isGeldigTelefoonnummer($waarden['telefoonnummer'])) {
            $fouten['telefoonnummer'] = 'Vul een geldig telefoonnummer in, bijvoorbeeld +31 6 12345678.';
        }

        if ($waarden['emailadres'] === '') {
            $fouten['emailadres'] = 'Vul een e-mailadres in.';
        } elseif (mb_strlen($waarden['emailadres']) > self::MAX_EMAILADRES) {
            $fouten['emailadres'] = 'Het e-mailadres mag maximaal ' . self::MAX_EMAILADRES . ' tekens bevatten.';
        } elseif (filter_var($waarden['emailadres'], FILTER_VALIDATE_EMAIL) === false) {
            $fouten['emailadres'] = 'Vul een geldig e-mailadres in, bijvoorbeeld naam@voorbeeld.nl.';
        }

        return $fouten;
    }

    /** True bij cijfers, spaties, +, - of haakjes, met minstens acht cijfers. */
    private static function isGeldigTelefoonnummer(string $waarde): bool
    {
        $cijfers = preg_replace('/\D+/', '', $waarde) ?? '';

        if (strlen($cijfers) < 8 || strlen($cijfers) > 15) {
            return false;
        }

        return preg_match('/^[0-9+\s\-()]+$/', $waarde) === 1;
    }

    private function zijnGeldigeIds(string $verkoperId, string $contactpersoonId): bool
    {
        return ctype_digit($verkoperId) && ctype_digit($contactpersoonId);
    }

    private function haalContactVanVerkoper(int $verkoperId, int $contactpersoonId): ?Contactpersoon
    {
        $contactpersoon = (new ContactpersoonModel())->haalDetails($contactpersoonId);

        if ($contactpersoon === null || $contactpersoon->verkoperId !== $verkoperId) {
            return null;
        }

        return $contactpersoon;
    }

    private function haalVerkoperNaam(int $verkoperId, int $contactpersoonId): string
    {
        try {
            $contactpersoon = $this->haalContactVanVerkoper($verkoperId, $contactpersoonId);

            return $contactpersoon?->verkoperNaam ?? '';
        } catch (DatabaseException) {
            return '';
        }
    }

    /**
     * @return array<string, string>
     */
    private static function leegFormulier(): array
    {
        return [
            'naam'           => '',
            'telefoonnummer' => '',
            'emailadres'     => '',
        ];
    }

    private function toonContactNietGevonden(string $verkoperId): void
    {
        $terugPad = ctype_digit($verkoperId)
            ? '/verkopers/' . $verkoperId . '/contact'
            : '/partners';

        $this->toon('fouten/melding', [
            'titel'       => 'Contactpersoon niet gevonden',
            'pad'         => $terugPad,
            'kop'         => 'Deze contactpersoon bestaat niet',
            'foutmelding' => 'De gevraagde contactpersoon is niet gevonden of hoort niet bij deze verkoper.',
        ], 404);
    }
}
