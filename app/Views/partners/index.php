<?php

declare(strict_types=1);

/**
 * Partneroverzicht: verkopers met een speciale status tonen logo en extra info.
 *
 * @var list<\App\Entities\Verkoper> $partners
 * @var string|null                  $melding
 * @var string|null                  $foutmelding
 */
?>
<section class="pagina-kop">
    <div class="container">
        <p class="pagina-kop__label">Partners</p>
        <h1 class="pagina-kop__titel">Zij maken Sneakerness mogelijk</h1>
        <p class="pagina-kop__tekst">
            Partners hebben een speciale status: zij krijgen een prominente stand en mogen hun
            logo en extra informatie op deze website tonen.
        </p>
    </div>
</section>

<section class="sectie">
    <div class="container">
        <?php if ($foutmelding !== null) : ?>
            <?= partial('melding', [
                'soort' => 'fout',
                'kop'   => 'De partners konden niet worden opgehaald',
                'tekst' => $foutmelding,
                'actieUrl'   => url('/partners'),
                'actieLabel' => 'Opnieuw proberen',
            ]) ?>
        <?php elseif ($melding !== null) : ?>
            <?= partial('melding', [
                'soort' => 'waarschuwing',
                'kop'   => 'Nog geen partners',
                'tekst' => $melding,
                'actieUrl'   => url('/stands'),
                'actieLabel' => 'Partner worden',
            ]) ?>
        <?php else : ?>
            <div class="raster raster--verkopers">
                <?php foreach ($partners as $partner) : ?>
                    <?= partial('verkoper-kaart', ['verkoper' => $partner, 'toonContactlink' => true]) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
