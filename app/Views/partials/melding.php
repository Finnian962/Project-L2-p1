<?php

declare(strict_types=1);

/**
 * Terugkoppeling aan de eindgebruiker bij een unhappy flow.
 *
 * @var string      $soort  info | waarschuwing | fout
 * @var string      $tekst  De melding zelf.
 * @var string|null $kop    Optionele kop boven de melding.
 * @var string|null $details Optionele technische toelichting (alleen in development).
 * @var string|null $actieUrl
 * @var string|null $actieLabel
 */

$soort = $soort ?? 'info';

$iconen = [
    'info'          => 'i',
    'waarschuwing'  => '!',
    'fout'          => '×',
];
?>
<div class="melding melding--<?= e($soort) ?>" role="<?= $soort === 'fout' ? 'alert' : 'status' ?>">
    <span class="melding__icoon" aria-hidden="true"><?= e($iconen[$soort] ?? 'i') ?></span>
    <div class="melding__inhoud">
        <?php if (!empty($kop)) : ?>
            <p class="melding__kop"><?= e($kop) ?></p>
        <?php endif; ?>
        <p class="melding__tekst"><?= e($tekst ?? '') ?></p>

        <?php if (!empty($details)) : ?>
            <p class="melding__details"><code><?= e($details) ?></code></p>
        <?php endif; ?>

        <?php if (!empty($actieUrl) && !empty($actieLabel)) : ?>
            <a class="knop knop--klein" href="<?= e($actieUrl) ?>"><?= e($actieLabel) ?></a>
        <?php endif; ?>
    </div>
</div>
