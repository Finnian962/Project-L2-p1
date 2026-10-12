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
}
