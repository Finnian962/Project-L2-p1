<?php

declare(strict_types=1);

/**
 * Detailpagina van één event.
 *
 * @var \App\Entities\Evenement                    $evenement
 * @var array<string, list<\App\Entities\Tijdslot>> $tijdsloten Gegroepeerd per dag.
 * @var list<\App\Entities\Standtype>              $standtypes
 * @var list<\App\Entities\Verkoper>               $verkopers
 * @var array<string, list<\App\Entities\Verkoper>> $sideStands
 */
?>
<section class="pagina-kop pagina-kop--event">
    <div class="container">
        <p class="kruimelpad"><a href="<?= e(url('/events')) ?>">Events</a> / <?= e($evenement->stad()) ?></p>
        <p class="pagina-kop__label"><?= e(periodeNl($evenement->startDatum(), $evenement->eindDatum())) ?></p>
        <h1 class="pagina-kop__titel"><?= e($evenement->naam) ?></h1>
        <p class="pagina-kop__tekst"><?= e($evenement->locatie) ?></p>

        <div class="kerncijfers">
            <div class="kerncijfer">
                <span class="kerncijfer__waarde"><?= e($evenement->aantalTicketsPerTijdslot) ?></span>
                <span class="kerncijfer__label">tickets per tijdslot</span>
            </div>
            <div class="kerncijfer">
                <span class="kerncijfer__waarde"><?= e($evenement->aantalTijdsloten) ?></span>
                <span class="kerncijfer__label">toegangstijden</span>
            </div>
            <div class="kerncijfer">
                <span class="kerncijfer__waarde"><?= e(max(0, $evenement->beschikbareTickets)) ?></span>
                <span class="kerncijfer__label">tickets beschikbaar</span>
            </div>
            <div class="kerncijfer">
                <span class="kerncijfer__waarde"><?= e($evenement->vrijeStands) ?></span>
                <span class="kerncijfer__label">vrije stands</span>
            </div>
        </div>

        <div class="pagina-kop__acties">
            <a class="knop" href="<?= e(url('/tickets?event=' . $evenement->id)) ?>">Bekijk tickets</a>
            <a class="knop knop--leeg" href="<?= e(url('/stands?event=' . $evenement->id)) ?>">Stand huren</a>
        </div>
    </div>
</section>

<section class="sectie">
    <div class="container">
        <h2 class="sectie__titel">Toegangstijden en prijzen</h2>

        <?php if ($tijdsloten === []) : ?>
            <?= partial('melding', [
                'soort' => 'waarschuwing',
                'kop'   => 'Nog geen toegangstijden',
                'tekst' => 'De organisator heeft voor dit event nog geen tijdsloten en prijzen ingesteld.',
            ]) ?>
        <?php else : ?>
            <?php foreach ($tijdsloten as $dag => $slotsVanDag) : ?>
                <h3 class="dag-kop"><?= e(datumNl($dag)) ?></h3>
                <div class="raster raster--tijdsloten">
                    <?php foreach ($slotsVanDag as $tijdslot) : ?>
                        <?= partial('tijdslot-kaart', ['tijdslot' => $tijdslot]) ?>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<section class="sectie sectie--gedempt">
    <div class="container">
        <h2 class="sectie__titel">Stands op deze editie</h2>

        <?php if ($standtypes === []) : ?>
            <?= partial('melding', [
                'soort' => 'waarschuwing',
                'kop'   => 'Nog geen stands',
                'tekst' => 'Voor dit event zijn nog geen stands beschikbaar gesteld.',
            ]) ?>
        <?php else : ?>
            <div class="tabel-omhulsel">
                <table class="tabel">
                    <caption class="visueel-verborgen">Standtypes met prijzen en beschikbaarheid</caption>
                    <thead>
                        <tr>
                            <th scope="col">Type</th>
                            <th scope="col">Omschrijving</th>
                            <th scope="col">Prijs per dag</th>
                            <th scope="col">Verhuurd</th>
                            <th scope="col">Vrij</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($standtypes as $standtype) : ?>
                            <tr>
                                <th scope="row"><span class="badge badge--geel"><?= e($standtype->standType) ?></span></th>
                                <td><?= e($standtype->omschrijving()) ?></td>
                                <td><?= e(euro($standtype->laagstePrijs)) ?></td>
                                <td><?= e($standtype->verhuurdeStands) ?> / <?= e($standtype->aantalStands) ?></td>
                                <td>
                                    <?php if ($standtype->isUitverkocht()) : ?>
                                        <span class="badge badge--rood">Volgeboekt</span>
                                    <?php else : ?>
                                        <?= e($standtype->vrijeStands) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="sectie">
    <div class="container">
        <h2 class="sectie__titel">Sneakerverkopers</h2>

        <?php if ($verkopers === []) : ?>
            <?= partial('melding', [
                'soort' => 'info',
                'tekst' => 'De verkoperslijst voor deze editie wordt binnenkort bekendgemaakt.',
            ]) ?>
        <?php else : ?>
            <div class="raster raster--verkopers">
                <?php foreach ($verkopers as $verkoper) : ?>
                    <?= partial('verkoper-kaart', ['verkoper' => $verkoper, 'toonContactlink' => true]) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($sideStands !== []) : ?>
    <section class="sectie sectie--gedempt">
        <div class="container">
            <h2 class="sectie__titel">Meer dan sneakers</h2>
            <p class="sectie__tekst">
                Eten, drinken, muziek en lifestyle: dit is er nog meer te doen tijdens deze editie.
            </p>

            <div class="raster raster--verkopers">
                <?php foreach ($sideStands as $soort => $standsVanSoort) : ?>
                    <?php foreach ($standsVanSoort as $verkoper) : ?>
                        <?= partial('verkoper-kaart', ['verkoper' => $verkoper]) ?>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>
