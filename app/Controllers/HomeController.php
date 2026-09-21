<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DatabaseException;
use App\Core\Logger;
use App\Core\Request;
use App\Models\EvenementModel;
use App\Models\VerkoperModel;

/**
 * Toont de homepagina en de informatiepagina van Sneakerness.
 */
final class HomeController extends Controller
{
    /**
     * GET /
     * Homepagina met het eerstvolgende event en een greep uit de partners.
     */
    public function index(Request $request): void
    {
        $gegevens = [
            'titel'        => 'Sneakerness® Rotterdam',
            'pad'          => '/',
            'evenement'    => null,
            'evenementen'  => [],
            'partners'     => [],
            'sideStands'   => [],
            'melding'      => null,
            'foutmelding'  => null,
        ];

        try {
            $evenementModel = new EvenementModel();
            $verkoperModel = new VerkoperModel();

            $evenementen = $evenementModel->haalOverzicht();
            $eerstvolgend = $evenementModel->haalEerstvolgende();

            $gegevens['evenementen'] = array_slice($evenementen, 0, 3);
            $gegevens['evenement'] = $eerstvolgend;
            $gegevens['partners'] = $verkoperModel->haalPartners();
            $gegevens['sideStands'] = $verkoperModel->haalSideStands();

            if ($evenementen === []) {
                // Unhappy flow: er is nog geen enkel event aangemaakt.
                $gegevens['melding'] = 'Er zijn nog geen events aangemaakt.';
                Logger::warning('Homepagina geladen zonder events');
            }
        } catch (DatabaseException $fout) {
            Logger::exception($fout, 'Homepagina kon de gegevens niet laden');
            $gegevens['foutmelding'] = $fout->getMessage();
        }

        $this->toon('home/index', $gegevens);
    }

    /**
     * GET /info
     * Praktische informatie over het evenement en de community.
     */
    public function info(Request $request): void
    {
        $this->toon('home/info', [
            'titel' => 'Praktische informatie',
            'pad'   => '/info',
        ]);
    }
}
