<?php

declare(strict_types=1);

/**
 * Kleine hulpfuncties die in de views gebruikt worden.
 *
 * Ze zijn bewust kort gehouden zodat de views leesbaar blijven.
 */

use App\Core\Request;
use App\Core\View;

if (!function_exists('partial')) {
    /**
     * Rendert een herbruikbaar stukje view uit app/Views/partials.
     *
     * @param array<string, mixed> $gegevens
     */
    function partial(string $naam, array $gegevens = []): string
    {
        return View::partial($naam, $gegevens);
    }
}

if (!function_exists('e')) {
    /**
     * Escapet gebruikersinvoer voor veilige weergave in HTML (tegen XSS).
     */
    function e(mixed $waarde): string
    {
        return htmlspecialchars((string) $waarde, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    /**
     * Bouwt een URL binnen de applicatie, inclusief het basispad.
     */
    function url(string $pad = '/'): string
    {
        $basis = Request::basisPad();
        $pad = '/' . ltrim($pad, '/');

        return $basis . ($pad === '/' ? '/' : rtrim($pad, '/'));
    }
}

if (!function_exists('asset')) {
    /**
     * Bouwt een URL naar een bestand in public/assets.
     */
    function asset(string $bestand): string
    {
        return url('assets/' . ltrim($bestand, '/'));
    }
}

if (!function_exists('euro')) {
    /**
     * Toont een bedrag in Nederlandse notatie: € 39,50.
     */
    function euro(mixed $bedrag): string
    {
        return '€ ' . number_format((float) $bedrag, 2, ',', '.');
    }
}

if (!function_exists('datumNl')) {
    /**
     * Toont een datum als '14 november 2026'.
     */
    function datumNl(?string $datum, bool $metJaar = true): string
    {
        if ($datum === null || $datum === '') {
            return 'datum onbekend';
        }

        $tijdstempel = strtotime($datum);

        if ($tijdstempel === false) {
            return 'datum onbekend';
        }

        $maanden = [
            1 => 'januari', 'februari', 'maart', 'april', 'mei', 'juni',
            'juli', 'augustus', 'september', 'oktober', 'november', 'december',
        ];

        $opmaak = (int) date('j', $tijdstempel) . ' ' . $maanden[(int) date('n', $tijdstempel)];

        return $metJaar ? $opmaak . ' ' . date('Y', $tijdstempel) : $opmaak;
    }
}

if (!function_exists('maandKort')) {
    /**
     * Geeft de maand in drie letters terug, bijvoorbeeld 'NOV'.
     */
    function maandKort(?string $datum): string
    {
        $tijdstempel = $datum === null ? false : strtotime($datum);

        if ($tijdstempel === false) {
            return '---';
        }

        $maanden = [
            1 => 'JAN', 'FEB', 'MRT', 'APR', 'MEI', 'JUN',
            'JUL', 'AUG', 'SEP', 'OKT', 'NOV', 'DEC',
        ];

        return $maanden[(int) date('n', $tijdstempel)];
    }
}

if (!function_exists('periodeNl')) {
    /**
     * Toont een periode compact: '14 – 15 november 2026'.
     */
    function periodeNl(?string $vanaf, ?string $tot): string
    {
        if ($vanaf === null || $vanaf === '') {
            return 'datum onbekend';
        }

        if ($tot === null || $tot === '' || $tot === $vanaf) {
            return datumNl($vanaf);
        }

        $zelfdeMaand = date('Y-m', (int) strtotime($vanaf)) === date('Y-m', (int) strtotime($tot));

        return $zelfdeMaand
            ? date('j', (int) strtotime($vanaf)) . ' – ' . datumNl($tot)
            : datumNl($vanaf, false) . ' – ' . datumNl($tot);
    }
}

if (!function_exists('tijdKort')) {
    /**
     * Toont een tijd als '10:00'.
     */
    function tijdKort(?string $tijd): string
    {
        if ($tijd === null || $tijd === '') {
            return '--:--';
        }

        return substr($tijd, 0, 5);
    }
}

if (!function_exists('isActiefMenu')) {
    /**
     * Bepaalt of een menu-item gemarkeerd moet worden als huidige pagina.
     */
    function isActiefMenu(string $pad, string $huidigPad): bool
    {
        if ($pad === '/') {
            return $huidigPad === '/';
        }

        return str_starts_with($huidigPad, $pad);
    }
}
