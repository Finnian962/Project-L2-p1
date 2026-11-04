<?php

declare(strict_types=1);

/**
 * De gegevens van een opgehaalde bestelling (sp_bestelling_ophalen), zoals
 * die op de wijzig- en annuleerpagina's en na een wijziging of annulering
 * getoond worden.
 *
 * @var array<string, mixed> $bestelling
 */

$geannuleerd = (int) ($bestelling['is_actief'] ?? 1) === 0;
?>
<ul class="bestelling">
    <li class="bestelling__regel">
        <span>Ticketnummer</span>
        <strong>#<?= e($bestelling['ticketnummer']) ?></strong>
    </li>

    <li class="bestelling__regel">
        <span>Editie</span>
        <strong><?= e($bestelling['evenement_naam']) ?></strong>
    </li>

    <li class="bestelling__regel">
        <span>Dag en tijdslot</span>
        <strong>
            <?= e(datumNl($bestelling['datum'])) ?>
            — <?= e(tijdKort($bestelling['tijdslot'])) ?>
        </strong>
    </li>

    <li class="bestelling__regel">
        <span>Tarief per ticket</span>
        <strong><?= e(euro($bestelling['tarief'])) ?></strong>
    </li>

    <li class="bestelling__regel">
        <span>Aantal tickets</span>
        <strong><?= e($bestelling['aantal']) ?></strong>
    </li>

    <li class="bestelling__regel bestelling__regel--totaal">
        <span>Totaal</span>
        <strong><?= e(euro($bestelling['totaal'])) ?></strong>
    </li>

    <li class="bestelling__regel">
        <span>Status</span>
        <strong><?= $geannuleerd ? 'Geannuleerd' : 'Geldig' ?></strong>
    </li>

    <li class="bestelling__regel">
        <span>Naam</span>
        <strong><?= e($bestelling['bezoeker_naam']) ?></strong>
    </li>

    <li class="bestelling__regel">
        <span>E-mailadres</span>
        <strong><?= e($bestelling['emailadres']) ?></strong>
    </li>
</ul>
