<?php

declare(strict_types=1);

/**
 * Overzicht van alle side-stands, gegroepeerd per soort.
 *
 * @var array<string, list<\App\Entities\Verkoper>> $perSoort
 * @var string|null                                 $melding
 * @var string|null                                 $foutmelding
 */
?>
<section class="pagina-kop">
    <div class="container">
        <p class="pagina-kop__label">Meer dan sneakers</p>
        <h1 class="pagina-kop__titel">Side-stands</h1>
        <p class="pagina-kop__tekst">
            Eten en drinken, een kinderhoek, customizers, tattoo-stands, barbershops en
            DJ-sets: het programma naast de sneakerstands.
        </p>
    </div>
</section>

<section class="sectie">
    <div class="container">
        <?php if ($foutmelding !== null) : ?>
            <?= partial('melding', [
                'soort' => 'fout',
                'kop'   => 'De side-stands konden niet worden opgehaald',
                'tekst' => $foutmelding,
                'actieUrl'   => url('/side-stands'),
                'actieLabel' => 'Opnieuw proberen',
            ]) ?>
        <?php elseif ($melding !== null) : ?>
            <?= partial('melding', [
                'soort' => 'waarschuwing',
                'kop'   => 'Nog geen side-stands',
                'tekst' => $melding,
                'actieUrl'   => url('/stands'),
                'actieLabel' => 'Zelf een stand huren',
            ]) ?>
        <?php else : ?>
            <?php foreach ($perSoort as $soort => $verkopers) : ?>
                <h2 class="sectie__titel"><?= e($soort) ?></h2>
                <div class="raster raster--verkopers">
                    <?php foreach ($verkopers as $verkoper) : ?>
                        <?= partial('verkoper-kaart', ['verkoper' => $verkoper]) ?>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
