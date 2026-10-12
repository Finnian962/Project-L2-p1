<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Bevat de gegevens van het binnenkomende HTTP-verzoek.
 */
final class Request
{
    /**
     * @param array<string, string> $queryParameters
     * @param array<string, string> $postParameters
     */
    private function __construct(
        private readonly string $methode,
        private readonly string $pad,
        private readonly array $queryParameters,
        private readonly array $postParameters = [],
    ) {
    }

    /**
     * Bouwt het verzoek op uit de PHP-superglobals.
     */
    public static function vanuitGlobals(): self
    {
        $methode = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $volledigePad = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

        $basisPad = self::basisPad();
        if ($basisPad !== '' && str_starts_with($volledigePad, $basisPad)) {
            $volledigePad = substr($volledigePad, strlen($basisPad));
        }

        $pad = '/' . trim(rawurldecode($volledigePad), '/');

        /** @var array<string, string> $query */
        $query = array_map('strval', $_GET);

        // Alleen gewone velden: een array-veld (bijv. titel[]) negeren we,
        // zodat validatie in de controller er melding van kan maken.
        /** @var array<string, string> $post */
        $post = array_filter($_POST, 'is_string');

        return new self($methode, $pad, $query, $post);
    }

    /**
     * Pad waarop de applicatie draait, bijvoorbeeld '' of '/project/public'.
     *
     * Hierdoor werkt de applicatie zowel via de ingebouwde PHP-server als
     * via Apache in een submap (WAMP/XAMPP).
     */
    public static function basisPad(): string
    {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

        // dirname() geeft op Windows een backslash terug; die maken we weer plat.
        $map = rtrim(str_replace('\\', '/', dirname($script)), '/');

        return $map === '/' ? '' : $map;
    }

    public function methode(): string
    {
        return $this->methode;
    }

    public function pad(): string
    {
        return $this->pad;
    }

    /**
     * Geeft een queryparameter terug (?zoek=rotterdam).
     */
    public function query(string $sleutel, string $standaard = ''): string
    {
        return trim($this->queryParameters[$sleutel] ?? $standaard);
    }

    /**
     * Geeft een queryparameter als positief geheel getal terug.
     */
    public function queryAlsGetal(string $sleutel, int $standaard = 0): int
    {
        $waarde = $this->query($sleutel);

        return ctype_digit($waarde) ? (int) $waarde : $standaard;
    }

    public function heeftQuery(string $sleutel): bool
    {
        return array_key_exists($sleutel, $this->queryParameters);
    }

    /**
     * Geeft een POST-veld uit een formulier terug, bijvoorbeeld de naam.
     *
     * De waarde wordt alvast opgeschoond (trim), zodat de controller alleen
     * nog hoeft te controleren of er iets ingevuld is.
     */
    public function post(string $sleutel, string $standaard = ''): string
    {
        return trim($this->postParameters[$sleutel] ?? $standaard);
    }

    public function heeftPost(string $sleutel): bool
    {
        return array_key_exists($sleutel, $this->postParameters);
    }
}
