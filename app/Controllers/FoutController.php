<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Request;
use Throwable;

/**
 * Toont nette foutpagina's aan de eindgebruiker.
 *
 * De bezoeker krijgt nooit een technische stacktrace te zien; die gaat naar
 * de technische log (storage/logs).
 */
final class FoutController extends Controller
{
    /**
     * 404: de gevraagde pagina bestaat niet.
     */
    public function nietGevonden(Request $request): void
    {
        $this->toon('fouten/melding', [
            'titel'       => 'Pagina niet gevonden',
            'pad'         => $request->pad(),
            'kop'         => 'Deze pagina bestaat niet',
            'foutmelding' => 'De pagina "' . $request->pad() . '" is niet gevonden. '
                . 'Gebruik het menu om verder te gaan.',
        ], 404);
    }

    /**
     * 500: er is iets misgegaan in de applicatie.
     */
    public function serverFout(Request $request, Throwable $fout): void
    {
        $toonDetails = (bool) Config::get('app.toon_fouten', false);

        $this->toon('fouten/melding', [
            'titel'       => 'Er ging iets mis',
            'pad'         => $request->pad(),
            'kop'         => 'Er ging iets mis aan onze kant',
            'foutmelding' => 'De pagina kon niet worden geladen. De fout is vastgelegd in de '
                . 'technische log; probeer het over een paar minuten opnieuw.',
            'details'     => $toonDetails ? $fout->getMessage() : null,
        ], 500);
    }
}
