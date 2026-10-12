<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DatabaseException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Session;
use App\Models\GebruikerModel;

/**
 * Inloggen en uitloggen van gebruikers (beheerders en standhouders).
 *
 * Happy flow: geldige gebruikersnaam en wachtwoord -> sessie opbouwen en
 * doorverwijzen naar de homepagina.
 * Unhappy flows: lege velden, onbekende combinatie of een database die niet
 * bereikbaar is -> een melding op het inlogformulier.
 */
final class AuthController extends Controller
{
    /** Het inlogformulier, met de laatste foutmelding (als die er is). */
    private const VIEW = 'auth/inloggen';

    /**
     * GET /inloggen
     * Toont het inlogformulier; wie al ingelogd is gaat direct naar home.
     */
    public function inloggenFormulier(Request $request): void
    {
        if (Auth::ingelogd()) {
            $this->redirect('/');
        }

        $this->toon(self::VIEW, [
            'titel'          => 'Inloggen',
            'pad'            => '/inloggen',
            'foutmelding'    => null,
            'gebruikersnaam' => '',
        ]);
    }

    /**
     * POST /inloggen
     * Controleert de inloggegevens en bouwt de sessie op.
     */
    public function inloggen(Request $request): void
    {
        if (Auth::ingelogd()) {
            $this->redirect('/');
        }

        $gebruikersnaam = $request->post('gebruikersnaam');
        $wachtwoord = $request->post('wachtwoord');

        // Unhappy flow: vergeten een veld in te vullen.
        if ($gebruikersnaam === '' || $wachtwoord === '') {
            $this->toonFormulier($gebruikersnaam, 'Vul zowel je gebruikersnaam als je wachtwoord in.', 422);

            return;
        }

        try {
            $account = (new GebruikerModel())->inloggen($gebruikersnaam, $wachtwoord);
        } catch (DatabaseException $fout) {
            // Unhappy flow: database niet bereikbaar.
            Logger::exception($fout, 'Inloggen mislukt: database niet bereikbaar');

            $this->toonFormulier(
                $gebruikersnaam,
                'Onze excuses, er is een error. We zijn ermee bezig.',
                503
            );

            return;
        }

        // Unhappy flow: onbekende gebruikersnaam of verkeerd wachtwoord.
        // Bewust dezelfde melding, zodat niet te raden valt welk account bestaat.
        if ($account === null) {
            $this->toonFormulier($gebruikersnaam, 'De gebruikersnaam en het wachtwoord kloppen niet.', 401);

            return;
        }

        Auth::inloggen($account);

        Session::melding(
            'Welkom terug, ' . Auth::naam() . '. Je bent ingelogd.',
            'info',
            'Ingelogd'
        );

        $this->redirect('/');
    }

    /**
     * POST /uitloggen
     * Verwijdert de sessie en stuurt de bezoeker terug naar de homepagina.
     */
    public function uitloggen(Request $request): void
    {
        if (Auth::ingelogd()) {
            $naam = Auth::naam();
            Auth::uitloggen();

            Session::melding('Je bent uitgelogd, ' . $naam . '.', 'info', 'Uitgelogd');
            Logger::info('Gebruiker uitgelogd', ['naam' => $naam]);
        }

        $this->redirect('/');
    }

    /**
     * Toont het inlogformulier opnieuw met een melding.
     */
    private function toonFormulier(string $gebruikersnaam, string $foutmelding, int $httpStatus): void
    {
        $this->toon(self::VIEW, [
            'titel'          => 'Inloggen',
            'pad'            => '/inloggen',
            'foutmelding'    => $foutmelding,
            'gebruikersnaam' => $gebruikersnaam,
        ], $httpStatus);
    }
}
