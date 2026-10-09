<?php

declare(strict_types=1);

/**
 * Kaart met één toegangstijd: tarief, beschikbaarheid en status.
 *
 * @var \App\Entities\Tijdslot $tijdslot
 * @var string|null $bestelUrl  Wanneer gevuld, de knop waarmee een bezoeker
 *                              tickets voor dit tijdslot kan bestellen.
 * @var string|null $boekUrl    Wanneer gevuld, de knop waarmee een medewerker
 *                              een ticket voor dit tijdslot boekt.
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
        <?php endif; ?>
        <!-- Altijd het aantal beschikbare tickets ten opzichte van het totaal. -->
        <?= e($tijdslot->beschikbareTickets) ?> van <?= e($tijdslot->capaciteit) ?> tickets beschikbaar
    </p>

    <?php if (!empty($bestelUrl) || !empty($boekUrl)) : ?>
        <div class="tijdslot__acties">
            <?php if (!empty($bestelUrl) && !$tijdslot->isUitverkocht()) : ?>
                <a class="knop knop--klein tijdslot__actie" href="<?= e($bestelUrl) ?>">
                    Bestellen
                </a>
            <?php endif; ?>

            <?php if (!empty($boekUrl)) : ?>
                <a class="knop knop--leeg knop--klein tijdslot__actie" href="<?= e($boekUrl) ?>">
                    Ticket boeken
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</article>
