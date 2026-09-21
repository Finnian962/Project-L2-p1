<?php

declare(strict_types=1);

/**
 * Toont de technische log van vandaag (nieuwste regel bovenaan).
 *
 * @var list<string> $regels
 * @var string|null  $melding
 */
?>
<section class="pagina-kop">
    <div class="container">
        <p class="kruimelpad"><a href="<?= e(url('/beheer')) ?>">Beheer</a> / Technische log</p>
        <p class="pagina-kop__label">Logging</p>
        <h1 class="pagina-kop__titel">Technische log</h1>
        <p class="pagina-kop__tekst">
            Elke actie in de applicatie wordt gelogd in <code>storage/logs/app-<?= e(date('Y-m-d')) ?>.log</code>:
            routing, aangeroepen stored procedures, waarschuwingen bij lege resultaten en fouten
            met de technische details. De bezoeker ziet alleen de nette melding op het scherm.
        </p>
    </div>
</section>

<section class="sectie">
    <div class="container">
        <?php if ($melding !== null) : ?>
            <?= partial('melding', ['soort' => 'info', 'tekst' => $melding]) ?>
        <?php else : ?>
            <p class="resultaat-telling">
                <strong><?= e(count($regels)) ?></strong> logregels · nieuwste bovenaan
            </p>

            <div class="logvenster">
                <?php foreach ($regels as $regel) : ?>
                    <?php
                    $niveau = 'info';
                    if (str_contains($regel, 'ERROR')) {
                        $niveau = 'fout';
                    } elseif (str_contains($regel, 'WARNING')) {
                        $niveau = 'waarschuwing';
                    } elseif (str_contains($regel, 'DEBUG')) {
                        $niveau = 'debug';
                    }
                    ?>
                    <p class="logregel logregel--<?= e($niveau) ?>"><?= e($regel) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
