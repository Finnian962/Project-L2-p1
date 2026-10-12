<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Basisklasse voor alle controllers.
 *
 * Een controller haalt gegevens op bij een model en geeft die door aan een view.
 */
abstract class Controller
{
    /**
     * Stuurt een view naar de browser.
     *
     * @param array<string, mixed> $gegevens
     */
    protected function toon(string $view, array $gegevens = [], int $httpStatus = 200): void
    {
        http_response_code($httpStatus);
        header('Content-Type: text/html; charset=utf-8');

        echo View::render($view, $gegevens);
    }

    /**
     * Stuurt de browser door naar een andere pagina (post/redirect/get).
     *
     * Na de doorverwijzing stopt het verzoek: er wordt niets meer verstuurd.
     */
    protected function redirect(string $pad, int $httpStatus = 302): void
    {
        http_response_code($httpStatus);
        header('Location: ' . url($pad));

        exit;
    }

    /**
     * Blokkeert een pagina voor bezoekers die niet ingelogd zijn of die een
     * andere rol hebben dan gevraagd.
     *
     * Niet ingelogd -> doorverwijzing naar de inlogpagina (met wachtmelding).
     * Andere rol    -> 403-pagina zonder inhoud.
     *
     * @param string ...$rollen Rollen die toegang hebben; leeg = elke rol.
     *
     * @return bool true wanneer de gebruiker verder mag, anders false.
     */
    protected function vereistRol(string ...$rollen): bool
    {
        if (!Auth::ingelogd()) {
            Session::melding(
                'Je moet ingelogd zijn om deze pagina te bekijken.',
                'waarschuwing',
                'Inloggen vereist'
            );
            Logger::info('Doorgestuurd naar de inlogpagina', ['bestemming' => '/inloggen']);

            $this->redirect('/inloggen');
        }

        if ($rollen !== [] && !Auth::heeftRol(...$rollen)) {
            Logger::warning('Geen toegang tot pagina', [
                'gebruiker' => Auth::gebruikersnaam(),
                'rol'       => Auth::rol(),
                'vereist'   => implode(', ', $rollen),
            ]);

            $this->toon('fouten/melding', [
                'kop'         => 'Geen toegang',
                'foutmelding' => 'Je bent ingelogd als "' . Auth::naam() . '", maar deze pagina is '
                    . 'alleen beschikbaar voor "' . implode(' of ', $rollen) . '". '
                    . 'Vraag de organisator om toegang.',
            ], 403);

            return false;
        }

        return true;
    }
}
