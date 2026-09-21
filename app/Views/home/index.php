<?php

declare(strict_types=1);

/**
 * Homepagina met het eerstvolgende event, de community-pijlers en partners.
 *
 * @var \App\Entities\Evenement|null   $evenement   Eerstvolgende editie.
 * @var list<\App\Entities\Evenement>  $evenementen Eerste drie edities.
 * @var list<\App\Entities\Verkoper>   $partners
 * @var list<\App\Entities\Verkoper>   $sideStands
 * @var string|null                    $melding
 * @var string|null                    $foutmelding
 */

/** De pijlers van de community, zoals beschreven in de opdracht. */
$pijlers = [
    ['titel' => 'Sneakers', 'tekst' => 'Kopen, verkopen en ruilen: van deadstock grails tot je eerste paar.'],
    ['titel' => 'Kunst',    'tekst' => 'Customizers en street-artists werken live op de vloer.'],
    ['titel' => 'Sport',    'tekst' => 'Panna-court, running crews en clinics met de community.'],
    ['titel' => 'Mode',     'tekst' => 'Shops, merken en vintage rails naast de sneakerstands.'],
    ['titel' => 'Muziek',   'tekst' => 'Doorlopende DJ-sets van vroege vogels tot sluitingstijd.'],
];
?>
<section class="hero">
    <div class="container hero__binnen">
        <div class="hero__tekst">
            <p class="hero__label">Van Nellefabriek · Rotterdam</p>
            <h1 class="hero__titel">
                Twee dagen <span class="markeer">sneakers</span>,
                kunst, sport, mode en muziek
            </h1>
            <p class="hero__intro">
                Sneakerness® is de ontmoetingsplaats van de sneaker-community. Liefhebbers, shops,
                privéverkopers, merken en partners komen samen om te kopen, verkopen en ruilen —
                met eten, muziek, customizers, tattoo-stands en barbershops op de achtergrond.
            </p>

            <div class="hero__acties">
                <a class="knop" href="<?= e(url('/tickets')) ?>">Tickets en tijdsloten</a>
                <a class="knop knop--leeg" href="<?= e(url('/stands')) ?>">Stand huren</a>
            </div>
        </div>

        <?php if ($evenement !== null) : ?>
            <aside class="hero__kaart" aria-label="Eerstvolgende editie">
                <p class="hero__kaart-label">Eerstvolgende editie</p>
                <p class="hero__kaart-titel"><?= e($evenement->naam) ?></p>
                <p class="hero__kaart-datum">
                    <?= e(periodeNl($evenement->startDatum(), $evenement->eindDatum())) ?>
                </p>
                <p class="hero__kaart-locatie"><?= e($evenement->locatie) ?></p>

                <dl class="hero__kaart-cijfers">
                    <div>
                        <dt>Tickets per tijdslot</dt>
                        <dd><?= e($evenement->aantalTicketsPerTijdslot) ?></dd>
                    </div>
                    <div>
                        <dt>Vanaf</dt>
                        <dd><?= e(euro($evenement->laagsteTarief ?? 0)) ?></dd>
                    </div>
                    <div>
                        <dt>Stands</dt>
                        <dd><?= e($evenement->beschikbareStands) ?></dd>
                    </div>
                </dl>

                <a class="knop knop--klein" href="<?= e(url('/events/' . $evenement->id)) ?>">
                    Bekijk programma
                </a>
            </aside>
        <?php endif; ?>
    </div>
</section>

<section class="sectie">
    <div class="container">
        <?php if ($foutmelding !== null) : ?>
            <?= partial('melding', [
                'soort' => 'fout',
                'kop'   => 'De gegevens konden niet worden opgehaald',
                'tekst' => $foutmelding,
                'actieUrl'   => url('/'),
                'actieLabel' => 'Opnieuw proberen',
            ]) ?>
        <?php elseif ($melding !== null) : ?>
            <?= partial('melding', [
                'soort' => 'waarschuwing',
                'kop'   => 'Nog geen edities gepland',
                'tekst' => $melding,
            ]) ?>
        <?php endif; ?>

        <h2 class="sectie__titel">Waar draait het om</h2>
        <div class="raster raster--pijlers">
            <?php foreach ($pijlers as $pijler) : ?>
                <article class="pijler">
                    <h3 class="pijler__titel"><?= e($pijler['titel']) ?></h3>
                    <p class="pijler__tekst"><?= e($pijler['tekst']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($evenementen !== []) : ?>
    <section class="sectie sectie--gedempt">
        <div class="container">
            <div class="sectie__kop">
                <h2 class="sectie__titel">Eerstvolgende edities</h2>
                <a class="sectie__link" href="<?= e(url('/events')) ?>">Bekijk alle events</a>
            </div>

            <div class="raster raster--events">
                <?php foreach ($evenementen as $komendEvenement) : ?>
                    <?= partial('evenement-kaart', ['evenement' => $komendEvenement]) ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($partners !== []) : ?>
    <section class="sectie">
        <div class="container">
            <div class="sectie__kop">
                <h2 class="sectie__titel">Onze partners</h2>
                <a class="sectie__link" href="<?= e(url('/partners')) ?>">Alle partners</a>
            </div>

            <div class="raster raster--verkopers">
                <?php foreach (array_slice($partners, 0, 3) as $partner) : ?>
                    <?= partial('verkoper-kaart', ['verkoper' => $partner]) ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($sideStands !== []) : ?>
    <section class="sectie sectie--gedempt">
        <div class="container">
            <div class="sectie__kop">
                <h2 class="sectie__titel">Side-stands</h2>
                <a class="sectie__link" href="<?= e(url('/side-stands')) ?>">Alle side-stands</a>
            </div>
            <p class="sectie__tekst">
                Eten en drinken, een kinderhoek, customizers, tattoo- en barbershops en DJ-sets:
                het hele weekend door.
            </p>

            <ul class="chips">
                <?php foreach ($sideStands as $sideStand) : ?>
                    <li class="chip">
                        <strong><?= e($sideStand->naam) ?></strong>
                        <span><?= e($sideStand->verkooptSoort) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
<?php endif; ?>
