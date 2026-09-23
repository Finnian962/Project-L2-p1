<?php

declare(strict_types=1);

/**
 * Verkopersoverzicht: alle shops en privéverkopers met een stand.
 *
 * Happy flow  : de kaarten van alle verkopers die aan een actief evenement
 *               gekoppeld zijn.
 * Unhappy flow: melding wanneer er geen verkopers zijn of de database faalt.
 *
 * @var string                      $titel
 * @var string                      $pad
 * @var list<\App\Entities\Verkoper> $verkopers
 * @var string                      $zoekterm
 * @var string                      $soort
 * @var string|null                 $melding
 * @var string|null                 $foutmelding
 */
?>
<section class="pagina-kop">
    <div class="container">
        <p class="pagina-kop__label">Verkopers</p>
        <h1 class="pagina-kop__titel">Wie staat er op de vloer?</h1>
        <p class="pagina-kop__tekst">
            Een overzicht van alle shops en privéverkopers met een stand op de
            komende editie: van sneakerstores tot customizers en streetfood.
        </p>
    </div>
</section>

<section class="sectie">
    <div class="container">
        <p class="resultaat-telling">
            <strong><?= e(count($verkopers)) ?></strong>
            <?= count($verkopers) === 1 ? 'verkoper gevonden' : 'verkopers gevonden' ?>
            · gesorteerd op soort en naam
        </p>

        <div class="raster raster--verkopers">
            <?php foreach ($verkopers as $verkoper) : ?>
                <?= partial('verkoper-kaart', ['verkoper' => $verkoper]) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
