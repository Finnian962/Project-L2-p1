<?php

declare(strict_types=1);

/**
 * Standaardlayout: navigatie, inhoud en footer.
 *
 * @var string $titel  Paginatitel.
 * @var string $pad    Huidig pad, voor het markeren van het menu-item.
 * @var string $inhoud De gerenderde view.
 */

use App\Core\Auth;
use App\Core\Session;

$huidigPad = $pad ?? '/';

/** Wie er ingelogd is (null wanneer niemand ingelogd is). */
$gebruiker = Auth::gebruiker();

/** Wachtmelding van de vorige actie (na een redirect), maar één keer tonen. */
$wachtMelding = Session::haalMelding();

/** Menustructuur van de website. */
$menu = [
    '/'                   => 'Home',
    '/events'             => 'Events',
    '/tickets'            => 'Tickets',
    '/tickets/toevoegen'  => 'Ticket toevoegen',
    '/stands'             => 'Stand huren',
    '/partners'           => 'Partners',
    '/side-stands'        => 'Side-stands',
    '/verkopers'          => 'Verkopers',
    '/info'               => 'Info',
];

// Standhouders krijgen een extra menu-item voor hun eigen reserveringen.
if (($gebruiker['rol'] ?? '') === 'standhouder') {
    $menu['/reserveringen'] = 'Reserveringen';
}

$menu['/beheer'] = 'Beheer';

// Alleen het meest specifieke menu-item wordt gemarkeerd, zodat
// '/tickets/toevoegen' niet tegelijk 'Tickets' activeert.
$actiefPad = null;

foreach ($menu as $menuPad => $label) {
    if (isActiefMenu($menuPad, $huidigPad)
        && ($actiefPad === null || strlen($menuPad) > strlen($actiefPad))
    ) {
        $actiefPad = $menuPad;
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Sneakerness® Rotterdam: tweedaags sneakerevent in de Van Nellefabriek. Tickets, stands, partners en side-stands.">
    <title><?= e($titel ?? 'Sneakerness®') ?> | Sneakerness®</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;600;800&family=Archivo+Black&display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
</head>
<body>
<a class="skip-link" href="#hoofdinhoud">Direct naar de inhoud</a>

<header class="site-header">
    <div class="container site-header__binnen">
        <a class="logo" href="<?= e(url('/')) ?>">
            <span class="logo__mark">SN</span>
            <span class="logo__tekst">Sneakerness<sup>®</sup></span>
        </a>

        <button class="menu-knop" type="button" aria-expanded="false" aria-controls="hoofdmenu">
            <span class="menu-knop__streep" aria-hidden="true"></span>
            <span class="visueel-verborgen">Menu openen</span>
        </button>

        <nav id="hoofdmenu" class="hoofdmenu" aria-label="Hoofdmenu">
            <ul class="hoofdmenu__lijst">
                <?php foreach ($menu as $menuPad => $label) : ?>
                    <?php $isActief = $menuPad === $actiefPad; ?>
                    <li>
                        <a
                            href="<?= e(url($menuPad)) ?>"
                            class="hoofdmenu__link<?= $isActief ? ' is-actief' : '' ?>"
                            <?= $isActief ? 'aria-current="page"' : '' ?>
                        ><?= e($label) ?></a>
                    </li>
                <?php endforeach; ?>

                <?php if ($gebruiker === null) : ?>
                    <li>
                        <a
                            href="<?= e(url('/inloggen')) ?>"
                            class="hoofdmenu__link<?= isActiefMenu('/inloggen', $huidigPad) ? ' is-actief' : '' ?>"
                            <?= isActiefMenu('/inloggen', $huidigPad) ? 'aria-current="page"' : '' ?>
                        >Inloggen</a>
                    </li>
                <?php else : ?>
                    <li class="hoofdmenu__account">
                        <span class="hoofdmenu__gebruiker">
                            <span class="visueel-verborgen">Ingelogd als </span><?= e($gebruiker['naam']) ?>
                        </span>
                        <form class="hoofdmenu__uitloggen" method="post" action="<?= e(url('/uitloggen')) ?>">
                            <button class="knop knop--leeg knop--klein" type="submit">Uitloggen</button>
                        </form>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>

<main id="hoofdinhoud">
    <?php if ($wachtMelding !== null) : ?>
        <div class="container">
            <?= partial('melding', $wachtMelding) ?>
        </div>
    <?php endif; ?>

    <?= $inhoud ?>
</main>

<footer class="site-footer">
    <div class="container site-footer__grid">
        <div>
            <p class="site-footer__titel">Sneakerness<sup>®</sup> Rotterdam</p>
            <p class="site-footer__tekst">
                Van Nellefabriek · Van Nelleweg 1 · Rotterdam<br>
                Twee dagen sneakers, kunst, sport, mode en muziek.
            </p>
        </div>
        <div>
            <p class="site-footer__titel">Snel naar</p>
            <ul class="site-footer__lijst">
                <li><a href="<?= e(url('/events')) ?>">Alle events</a></li>
                <li><a href="<?= e(url('/tickets')) ?>">Tickets en tijdsloten</a></li>
                <li><a href="<?= e(url('/stands')) ?>">Stand huren</a></li>
                <li><a href="<?= e(url('/beheer/log')) ?>">Technische log</a></li>
            </ul>
        </div>
        <div>
            <p class="site-footer__titel">Contact</p>
            <ul class="site-footer__lijst">
                <li><a href="mailto:info@sneakerness.nl">info@sneakerness.nl</a></li>
                <li><a href="mailto:stands@sneakerness.nl">stands@sneakerness.nl</a></li>
                <li><a href="tel:+31101234567">+31 10 123 45 67</a></li>
            </ul>
        </div>
    </div>
    <div class="container site-footer__onder">
        <p>© <?= date('Y') ?> Sneakerness® — schoolproject Sprint 1 (read-functionaliteit).</p>
    </div>
</footer>

<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
