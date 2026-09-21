<?php

declare(strict_types=1);

/**
 * Algemene foutpagina (404, 500 en 503).
 *
 * @var string      $kop
 * @var string      $foutmelding
 * @var string|null $details Alleen zichtbaar in de development-omgeving.
 */
?>
<section class="sectie sectie--fout">
    <div class="container container--smal">
        <p class="pagina-kop__label">Melding</p>
        <h1 class="pagina-kop__titel"><?= e($kop) ?></h1>

        <?= partial('melding', [
            'soort'   => 'fout',
            'tekst'   => $foutmelding,
            'details' => $details ?? null,
        ]) ?>

        <div class="pagina-kop__acties">
            <a class="knop" href="<?= e(url('/')) ?>">Naar de homepagina</a>
            <a class="knop knop--leeg" href="<?= e(url('/events')) ?>">Bekijk alle events</a>
        </div>
    </div>
</section>
