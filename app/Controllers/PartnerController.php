<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DatabaseException;
use App\Core\Logger;
use App\Core\Request;
use App\Models\ContactpersoonModel;
use App\Models\VerkoperModel;

/**
 * Toont partners (met logo en extra informatie) en de side-stands:
 * eten en drinken, kids corner, customizers, tattoo, barbershop en DJ-sets.
 */
final class PartnerController extends Controller
{
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
            'titel'          => 'Contactpersonen',
            'pad'            => '/partners',
            'contactpersonen' => [],
            'melding'        => null,
            'foutmelding'    => null,
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
}
