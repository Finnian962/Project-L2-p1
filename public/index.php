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

use App\Controllers\AuthController;
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

// Inloggen en uitloggen (beheerders en standhouders)
$router->get('/inloggen', AuthController::class, 'inloggenFormulier');
$router->post('/inloggen', AuthController::class, 'inloggen');
$router->post('/uitloggen', AuthController::class, 'uitloggen');

// Publieke pagina's
$router->get('/', HomeController::class, 'index');
$router->get('/events', EvenementController::class, 'index');
$router->get('/events/{id}', EvenementController::class, 'detail');
$router->get('/tickets', TicketController::class, 'index');
$router->get('/tickets/toevoegen', TicketController::class, 'toevoegen');
$router->post('/tickets/toevoegen', TicketController::class, 'opslaan');
$router->get('/tickets/boeken', TicketController::class, 'boeken');
$router->post('/tickets/boeken', TicketController::class, 'boekOp');
$router->get('/tickets/bestellen', TicketController::class, 'bestellen');
$router->post('/tickets/bestellen', TicketController::class, 'controleerBestelling');
$router->post('/tickets/betalen', TicketController::class, 'betalen');
$router->get('/stands', StandController::class, 'index');
$router->get('/stands/beschikbaar', StandController::class, 'beschikbaar');
$router->post('/stands/reserveren', StandController::class, 'reserveren');
$router->get('/reserveringen', StandController::class, 'reserveringen');
$router->get('/partners', PartnerController::class, 'index');
$router->get('/side-stands', PartnerController::class, 'sideStands');
$router->get('/verkopers', VerkoperController::class, 'index');
$router->get('/verkopers/nieuw', VerkoperController::class, 'nieuw');
$router->post('/verkopers/nieuw', VerkoperController::class, 'opslaan');
$router->get('/verkopers/{id}/contact', PartnerController::class, 'contactpersonen');
$router->get('/info', HomeController::class, 'info');

// Beheeromgeving van de organisator (alleen voor beheerders)
$router->get('/beheer', BeheerController::class, 'index');
// Event toevoegen: alleen voor ingelogde beheerders (happy + unhappy flows)
$router->get('/beheer/events/toevoegen', EvenementController::class, 'nieuw');
$router->post('/beheer/events/toevoegen', EvenementController::class, 'opslaan');
$router->get('/beheer/stands', BeheerController::class, 'stands');
$router->get('/beheer/stands/toevoegen', BeheerController::class, 'standToevoegen');
$router->post('/beheer/stands/toevoegen', BeheerController::class, 'standOpslaan');
$router->get('/beheer/log', BeheerController::class, 'log');

try {
    $router->verwerk($request);
} catch (Throwable $fout) {
    // Alles wat onderweg misgaat, eindigt hier: loggen en een nette 500-pagina.
    Logger::exception($fout, 'Onafgehandelde fout tijdens verwerken verzoek');

    (new FoutController())->serverFout($request, $fout);
}
