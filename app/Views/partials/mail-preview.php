<?php

declare(strict_types=1);

/**
 * De inhoud van een e-mail die naar de bezoeker verstuurd is, zoals die op de
 * bevestigingspagina getoond wordt.
 *
 * Er is geen mailserver in dit project: de tekst gaat naar de technische log
 * (Logger) en de bezoeker ziet hem hier terug op het scherm.
 *
 * @var string $aan       Het e-mailadres van de ontvanger.
 * @var string $onderwerp De onderwerpregel van de mail.
 * @var string $tekst     De tekst van de mail, met regelafbrekingen.
 */
?>
<div class="mail">
    <p class="mail__kop">Verstuurde mail</p>

    <dl class="mail__koppen">
        <div>
            <dt>Aan</dt>
            <dd><?= e($aan) ?></dd>
        </div>
        <div>
            <dt>Onderwerp</dt>
            <dd><?= e($onderwerp) ?></dd>
        </div>
    </dl>

    <div class="mail__tekst">
        <?php foreach (preg_split('/\R/', trim($tekst)) ?: [] as $regel) : ?>
            <p><?= $regel === '' ? '&nbsp;' : e($regel) ?></p>
        <?php endforeach; ?>
    </div>
</div>
