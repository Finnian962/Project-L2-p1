<?php

declare(strict_types=1);

/**
 * Kaart met één toegangstijd: tarief, beschikbaarheid en status.
 *
 * @var \App\Entities\Tijdslot $tijdslot
 */
?>
<article class="tijdslot<?= $tijdslot->isUitverkocht() ? ' tijdslot--uitverkocht' : '' ?>">
    <div class="tijdslot__tijd">
        <span class="tijdslot__klok"><?= e(tijdKort($tijdslot->tijdslot)) ?></span>
        <?php if (!empty($tijdslot->opmerking)) : ?>
            <span class="badge badge--geel"><?= e($tijdslot->opmerking) ?></span>
        <?php endif; ?>
    </div>

    <p class="tijdslot__tarief"><?= e(euro($tijdslot->tarief)) ?></p>

    <div class="voortgang" aria-hidden="true">
        <div class="voortgang__balk" style="width: <?= e($tijdslot->bezettingsgraad()) ?>%"></div>
    </div>

    <p class="tijdslot__status">
        <?php if ($tijdslot->isUitverkocht()) : ?>
            <span class="badge badge--rood">Uitverkocht</span>
        <?php elseif ($tijdslot->isBijnaVol()) : ?>
            <span class="badge badge--oranje">Nog <?= e($tijdslot->beschikbareTickets) ?> plaatsen</span>
        <?php else : ?>
            <?= e($tijdslot->beschikbareTickets) ?> van <?= e($tijdslot->capaciteit) ?> tickets beschikbaar
        <?php endif; ?>
    </p>
</article>
