<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Rendert een view binnen een layout (de V van MVC).
 *
 * Views bevatten uitsluitend presentatielogica; alle gegevens komen als
 * array binnen vanuit de controller.
 */
final class View
{
    private const VIEW_MAP = __DIR__ . '/../Views/';

    /**
     * Toont een view met de standaardlayout.
     *
     * @param array<string, mixed> $gegevens
     *
     * @throws RuntimeException Wanneer het viewbestand niet bestaat.
     */
    public static function render(string $view, array $gegevens = [], string $layout = 'main'): string
    {
        $inhoud = self::rendeBestand(self::VIEW_MAP . $view . '.php', $gegevens);

        if ($layout === '') {
            return $inhoud;
        }

        return self::rendeBestand(
            self::VIEW_MAP . 'layouts/' . $layout . '.php',
            array_merge($gegevens, ['inhoud' => $inhoud])
        );
    }

    /**
     * Rendert een losse partial (bijvoorbeeld een kaartje of een melding).
     *
     * @param array<string, mixed> $gegevens
     */
    public static function partial(string $partial, array $gegevens = []): string
    {
        return self::rendeBestand(self::VIEW_MAP . 'partials/' . $partial . '.php', $gegevens);
    }

    /**
     * Voert één PHP-viewbestand uit en vangt de uitvoer op.
     *
     * @param array<string, mixed> $gegevens
     *
     * @throws RuntimeException Wanneer het bestand ontbreekt.
     */
    private static function rendeBestand(string $bestandspad, array $gegevens): string
    {
        if (!is_file($bestandspad)) {
            Logger::error('View niet gevonden', ['bestand' => $bestandspad]);

            throw new RuntimeException('View niet gevonden: ' . $bestandspad);
        }

        extract($gegevens, EXTR_SKIP);

        ob_start();
        require $bestandspad;

        return (string) ob_get_clean();
    }
}
