<?php

declare(strict_types=1);

/**
 * Praktische informatie over het evenement, de doelgroep en de rollen.
 */

/** Veelgestelde vragen, direct afgeleid van de opdrachtomschrijving. */
$vragen = [
    [
        'vraag'    => 'Waarom is vroege toegang duurder?',
        'antwoord' => 'Wie als eerste binnen is, heeft de meeste keuze uit de zeldzame paren. '
            . 'Daarom loopt het tarief per tijdslot af: het early access ticket is het duurst, '
            . 'het laatste tijdslot het goedkoopst.',
    ],
    [
        'vraag'    => 'Kan ik als privéverkoper een stand huren?',
        'antwoord' => 'Ja. Standtype A is bedoeld voor privéverkopers die hun eigen paren willen '
            . 'verkopen of ruilen. Je huurt voor één dag of voor beide dagen.',
    ],
    [
        'vraag'    => 'Wat krijg ik extra als partner?',
        'antwoord' => 'Partners hebben een speciale status: zij krijgen een AA+ of AA stand op de '
            . 'beste positie en mogen hun logo plus extra informatie op deze website tonen.',
    ],
    [
        'vraag'    => 'Waarom vragen jullie mijn contactgegevens?',
        'antwoord' => 'Naam, e-mailadres en (bij verkopers) telefoonnummer gebruiken we uitsluitend '
            . 'om je te bereiken bij wijzigingen, annuleringen of praktische informatie over '
            . 'opbouw- en afbouwtijden.',
    ],
];

/** De drie gebruikersrollen uit de analyse. */
$rollen = [
    ['rol' => 'Bezoeker', 'tekst' => 'Koopt tickets voor een dag en tijdslot en beheert de eigen contactgegevens.'],
    ['rol' => 'Verkoper', 'tekst' => 'Huurt een stand (AA+, AA of A) voor één of twee dagen en legt contactpersonen vast.'],
    ['rol' => 'Organisator', 'tekst' => 'Beheert events, tijdsloten, capaciteit en stands en houdt de voortgang bij.'],
];
?>
<section class="pagina-kop">
    <div class="container">
        <p class="pagina-kop__label">Praktisch</p>
        <h1 class="pagina-kop__titel">Alles over Sneakerness®</h1>
        <p class="pagina-kop__tekst">
            Sneakerness® is een tweedaags evenement in de Van Nellefabriek in Rotterdam en reist
            jaarlijks langs meerdere Europese steden. Het doel: een ontmoetingsplaats voor de
            sneaker-community — een diverse groep mensen met interesse in sneakers, kunst, sport,
            mode en muziek.
        </p>
    </div>
</section>

<section class="sectie">
    <div class="container">
        <h2 class="sectie__titel">Voor wie</h2>
        <div class="raster raster--pijlers">
            <?php foreach ($rollen as $rol) : ?>
                <article class="pijler">
                    <h3 class="pijler__titel"><?= e($rol['rol']) ?></h3>
                    <p class="pijler__tekst"><?= e($rol['tekst']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="sectie sectie--gedempt">
    <div class="container">
        <h2 class="sectie__titel">Veelgestelde vragen</h2>
        <div class="vragen">
            <?php foreach ($vragen as $index => $vraag) : ?>
                <details class="vraag"<?= $index === 0 ? ' open' : '' ?>>
                    <summary class="vraag__kop"><?= e($vraag['vraag']) ?></summary>
                    <p class="vraag__tekst"><?= e($vraag['antwoord']) ?></p>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="sectie">
    <div class="container container--smal">
        <h2 class="sectie__titel">Locatie</h2>
        <p class="sectie__tekst">
            Van Nellefabriek · Van Nelleweg 1 · 3044 BC Rotterdam. Het terrein is bereikbaar met
            metro en bus; er is beperkte parkeergelegenheid op het terrein zelf.
        </p>
        <p class="sectie__tekst">
            Vragen over tickets? Mail naar <a href="mailto:info@sneakerness.nl">info@sneakerness.nl</a>.
            Vragen over stands? Mail naar <a href="mailto:stands@sneakerness.nl">stands@sneakerness.nl</a>.
        </p>
    </div>
</section>
