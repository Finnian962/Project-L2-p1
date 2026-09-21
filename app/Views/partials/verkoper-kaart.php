<?php

declare(strict_types=1);

/**
 * Kaart met één verkoper, partner of side-stand.
 *
 * @var \App\Entities\Verkoper $verkoper
 * @var bool                   $toonContactlink
 */

$toonContactlink = $toonContactlink ?? false;
?>
<article class="kaart kaart--verkoper">
    <div class="verkoper__logo">
        <?php if ($verkoper->logoPad() !== null) : ?>
            <img src="<?= e($verkoper->logoPad()) ?>" alt="Logo van <?= e($verkoper->naam) ?>" loading="lazy">
        <?php else : ?>
            <span class="verkoper__initialen" aria-hidden="true"><?= e($verkoper->initialen()) ?></span>
        <?php endif; ?>
    </div>

    <div class="kaart__inhoud">
        <p class="kaart__label"><?= e($verkoper->verkooptSoort) ?></p>
        <h3 class="kaart__titel"><?= e($verkoper->naam) ?></h3>

        <?php if ($verkoper->isPartner) : ?>
            <span class="badge badge--geel">Partner</span>
        <?php endif; ?>
        <span class="badge">Stand <?= e($verkoper->standType) ?></span>
        <span class="badge"><?= e($verkoper->dagenOmschrijving()) ?></span>

        <?php if (!empty($verkoper->beschrijving)) : ?>
            <p class="kaart__tekst"><?= e($verkoper->beschrijving) ?></p>
        <?php endif; ?>

        <?php if ($toonContactlink) : ?>
            <a class="kaart__link" href="<?= e(url('/verkopers/' . $verkoper->id . '/contact')) ?>">
                Contactpersonen (<?= e($verkoper->aantalContactpersonen) ?>)
            </a>
        <?php endif; ?>
    </div>
</article>
