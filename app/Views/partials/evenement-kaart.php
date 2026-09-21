<?php

declare(strict_types=1);

/**
 * Kaart met de belangrijkste gegevens van één event.
 *
 * @var \App\Entities\Evenement        $evenement
 * @var list<\App\Entities\Tijdslot>   $tijdsloten Optioneel: tijdsloten van dit event.
 */

$tijdsloten = $tijdsloten ?? [];
?>
<article class="kaart kaart--event">
    <div class="kaart__datum" aria-hidden="true">
        <span class="kaart__dag"><?= e(date('j', (int) strtotime($evenement->startDatum()))) ?></span>
        <span class="kaart__maand"><?= e(maandKort($evenement->startDatum())) ?></span>
    </div>

    <div class="kaart__inhoud">
        <p class="kaart__label"><?= e($evenement->stad()) ?></p>
        <h3 class="kaart__titel">
            <a href="<?= e(url('/events/' . $evenement->id)) ?>"><?= e($evenement->naam) ?></a>
        </h3>

        <ul class="kaart__gegevens">
            <li><span class="kaart__term">Datum</span>
                <?= e(periodeNl($evenement->startDatum(), $evenement->eindDatum())) ?>
                <?php if ($evenement->aantalDagen > 1) : ?>
                    <span class="badge badge--klein"><?= e($evenement->aantalDagen) ?> dagen</span>
                <?php endif; ?>
            </li>
            <li><span class="kaart__term">Locatie</span> <?= e($evenement->locatie) ?></li>
            <li><span class="kaart__term">Tickets per tijdslot</span>
                <?= e($evenement->aantalTicketsPerTijdslot) ?>
                <?php if ($evenement->heeftTijdsloten()) : ?>
                    (<?= e($evenement->aantalTijdsloten) ?> tijdsloten)
                <?php endif; ?>
            </li>
            <li><span class="kaart__term">Nog beschikbaar</span>
                <?php if ($evenement->isUitverkocht()) : ?>
                    <span class="badge badge--rood">Uitverkocht</span>
                <?php else : ?>
                    <?= e(max(0, $evenement->beschikbareTickets)) ?> tickets
                <?php endif; ?>
            </li>
        </ul>

        <?php if ($tijdsloten !== []) : ?>
            <p class="kaart__term kaart__term--blok">Beschikbaar per tijdslot</p>
            <ul class="slotjes">
                <?php foreach ($tijdsloten as $tijdslot) : ?>
                    <li class="slotje<?= $tijdslot->isUitverkocht() ? ' slotje--uitverkocht' : '' ?>">
                        <span class="slotje__dag"><?= e(date('d-m', (int) strtotime($tijdslot->datum))) ?></span>
                        <span class="slotje__tijd"><?= e(tijdKort($tijdslot->tijdslot)) ?></span>
                        <span class="slotje__aantal">
                            <?= $tijdslot->isUitverkocht() ? 'vol' : e($tijdslot->beschikbareTickets) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($evenement->heeftTijdsloten()) : ?>
            <div class="voortgang" aria-hidden="true">
                <div class="voortgang__balk" style="width: <?= e($evenement->bezettingsgraad()) ?>%"></div>
            </div>
            <p class="kaart__voetnoot">
                <?= e($evenement->bezettingsgraad()) ?>% verkocht ·
                tickets vanaf <?= e(euro($evenement->laagsteTarief ?? 0)) ?>
            </p>
        <?php else : ?>
            <p class="kaart__voetnoot">Toegangstijden en prijzen worden binnenkort bekendgemaakt.</p>
        <?php endif; ?>

        <div class="kaart__acties">
            <a class="knop knop--klein" href="<?= e(url('/events/' . $evenement->id)) ?>">Bekijk event</a>
            <a class="knop knop--klein knop--leeg" href="<?= e(url('/tickets?event=' . $evenement->id)) ?>">Tickets</a>
        </div>
    </div>
</article>
