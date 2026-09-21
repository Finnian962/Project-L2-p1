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
}
