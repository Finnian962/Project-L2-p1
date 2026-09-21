<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\FoutController;

/**
 * Koppelt een URL aan een controller en een methode (de C van MVC).
 *
 * Een patroon mag een parameter bevatten tussen accolades, bijvoorbeeld
 * '/events/{id}'. De waarde daarvan wordt als argument aan de controller
 * meegegeven.
 */
final class Router
{
    /** @var list<array{methode: string, patroon: string, controller: class-string, actie: string}> */
    private array $routes = [];

    /**
     * Registreert een GET-route.
     *
     * @param class-string $controller
     */
    public function get(string $patroon, string $controller, string $actie): void
    {
        $this->routes[] = [
            'methode'    => 'GET',
            'patroon'    => $patroon,
            'controller' => $controller,
            'actie'      => $actie,
        ];
    }

    /**
     * Zoekt de bijpassende route en voert de controlleractie uit.
     * Wanneer geen route past, toont de FoutController een 404-pagina.
     */
    public function verwerk(Request $request): void
    {
        foreach ($this->routes as $route) {
            if ($route['methode'] !== $request->methode()) {
                continue;
            }

            $parameters = $this->vergelijk($route['patroon'], $request->pad());

            if ($parameters === null) {
                continue;
            }

            Logger::info('Route gevonden', [
                'pad'        => $request->pad(),
                'controller' => $route['controller'],
                'actie'      => $route['actie'],
            ]);

            $controller = new $route['controller']();
            $controller->{$route['actie']}($request, ...array_values($parameters));

            return;
        }

        Logger::warning('Geen route gevonden', [
            'methode' => $request->methode(),
            'pad'     => $request->pad(),
        ]);

        (new FoutController())->nietGevonden($request);
    }

    /**
     * Vergelijkt een routepatroon met het opgevraagde pad.
     *
     * @return array<string, string>|null De gevonden parameters, of null bij geen match.
     */
    private function vergelijk(string $patroon, string $pad): ?array
    {
        $regex = '#^' . preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $patroon) . '$#';

        if (preg_match($regex, $pad, $treffers) !== 1) {
            return null;
        }

        return array_filter($treffers, static fn (string|int $sleutel): bool => is_string($sleutel), ARRAY_FILTER_USE_KEY);
    }
}
