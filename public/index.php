<?php

declare(strict_types=1);

/**
 * Front controller: elk verzoek aan de webapplicatie komt hier binnen.
 *
 * Verantwoordelijkheden:
 *  1. applicatie opstarten (bootstrap);
 *  2. routes registreren;
 *  3. het verzoek laten afhandelen door de juiste controller;
 *  4. onverwachte fouten opvangen en als nette melding tonen.
 */

use App\Controllers\BeheerController;
use App\Controllers\EvenementController;
use App\Controllers\FoutController;
use App\Controllers\HomeController;
use App\Controllers\PartnerController;
use App\Controllers\StandController;
use App\Controllers\TicketController;
use App\Controllers\VerkoperController;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Router;

require __DIR__ . '/../app/bootstrap.php';

$request = Request::vanuitGlobals();

$router = new Router();

// Publieke pagina's
$router->get('/', HomeController::class, 'index');
$router->get('/events', EvenementController::class, 'index');
$router->get('/events/{id}', EvenementController::class, 'detail');
$router->get('/tickets', TicketController::class, 'index');
$router->get('/stands', StandController::class, 'index');
$router->get('/partners', PartnerController::class, 'index');
$router->get('/side-stands', PartnerController::class, 'sideStands');
$router->get('/verkopers', VerkoperController::class, 'index');
$router->get('/verkopers/{id}/contact', PartnerController::class, 'contactpersonen');
$router->get('/info', HomeController::class, 'info');

// Beheeromgeving van de organisator
$router->get('/beheer', BeheerController::class, 'index');
$router->get('/beheer/log', BeheerController::class, 'log');

try {
    $router->verwerk($request);
} catch (Throwable $fout) {
    // Alles wat onderweg misgaat, eindigt hier: loggen en een nette 500-pagina.
    Logger::exception($fout, 'Onafgehandelde fout tijdens verwerken verzoek');

    (new FoutController())->serverFout($request, $fout);
}
