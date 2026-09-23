<?php

declare(strict_types=1);

/**
 * Verkopersoverzicht: alle shops en privéverkopers met een stand.
 *
 * Happy flow  : de kaarten van alle verkopers die aan een actief evenement
 *               gekoppeld zijn.
 * Unhappy flow: melding wanneer er geen verkopers zijn of de database faalt.
 *
 * @var string                       $titel
 * @var string                       $pad
 * @var list<\App\Entities\Verkoper> $verkopers
 * @var string                       $zoekterm
 * @var string                       $soort
 * @var string|null                  $melding
 * @var string|null                  $foutmelding
 */

/** Productsoorten waarop gefilterd kan worden. */
$soorten = [
    ''                => 'Alle',
    'Sneakers'        => 'Sneakers',
    'Lifestyle'       => 'Lifestyle',
    'Eten en Drinken' => 'Eten en Drinken',
    'Kids Corner'     => 'Kids Corner',
    'Customizer'      => 'Customizer',
    'Tattoo'          => 'Tattoo',
    'Barbershop'      => 'Barbershop',
    'DJ-set'          => 'DJ-set',
];

/** Link naar het overzicht met behoud van de actieve filters. */
$maakLink = static function (string $waarde) use ($zoekterm, $soort): string {
    $parameters = array_filter(
        ['soort' => $waarde, 'zoek' => $zoekterm],
        static fn (string $waarde2): bool => $waarde2 !== ''
    );

    return url('/verkopers') . ($parameters === [] ? '' : '?' . http_build_query($parameters));
};
?>
<section class="pagina-kop">
    <div class="container">
        <p class="pagina-kop__label">Verkopers</p>
        <h1 class="pagina-kop__titel">Wie staat er op de vloer?</h1>
        <p class="pagina-kop__tekst">
            Een overzicht van alle shops en privéverkopers met een stand op de
            komende editie: van sneakerstores tot customizers en streetfood.
        </p>

        <div class="kiezer">
            <span class="kiezer__label">Filter op soort</span>
            <?php foreach ($soorten as $waarde => $label) : ?>
                <a
                    class="knop<?= $soort === $waarde ? '' : ' knop--leeg' ?>"
                    href="<?= e($maakLink($waarde)) ?>"
                    <?= $soort === $waarde ? 'aria-current="true"' : '' ?>
                ><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="sectie">
    <div class="container">
        <?php if ($foutmelding !== null) : ?>
            <?= partial('melding', [
                'soort' => 'fout',
                'kop'   => 'De verkopers konden niet worden opgehaald',
                'tekst' => $foutmelding,
                'actieUrl'   => url('/verkopers'),
                'actieLabel' => 'Opnieuw proberen',
            ]) ?>
        <?php elseif ($melding !== null) : ?>
            <?= partial('melding', [
                'soort' => 'waarschuwing',
                'kop'   => 'Nog geen verkopers',
                'tekst' => $melding,
                'actieUrl'   => url('/stands'),
                'actieLabel' => 'Stand aanvragen',
            ]) ?>
        <?php else : ?>
            <p class="resultaat-telling">
                <strong><?= e(count($verkopers)) ?></strong>
                <?= count($verkopers) === 1 ? 'verkoper gevonden' : 'verkopers gevonden' ?>
                <?= $soort !== '' ? ' voor "' . e($soort) . '"' : '' ?>
                · gesorteerd op soort en naam
            </p>

            <div class="raster raster--verkopers">
                <?php foreach ($verkopers as $verkoper) : ?>
                    <?= partial('verkoper-kaart', ['verkoper' => $verkoper]) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
