<?php

declare(strict_types=1);

/**
 * Ticketpagina: toegangstijden met tarief en beschikbaarheid per dag.
 *
 * @var list<\App\Entities\Evenement>               $evenementen
 * @var \App\Entities\Evenement|null                $evenement
 * @var array<string, list<\App\Entities\Tijdslot>> $tijdsloten
 * @var string|null                                 $melding
 * @var string|null                                 $foutmelding
 */
?>
<section class="pagina-kop">
    <div class="container">
        <p class="pagina-kop__label">Tickets</p>
        <h1 class="pagina-kop__titel">Kies je toegangstijd</h1>
        <p class="pagina-kop__tekst">
            Hoe vroeger je binnen bent, hoe groter de kans op die ene grail — daarom is vroege
            toegang duurder dan een ticket later op de dag. Elk tijdslot heeft een eigen
            capaciteit, zodat het nooit té druk wordt.
        </p>

        <?php if ($evenementen !== []) : ?>
            <form class="kiezer" method="get" action="<?= e(url('/tickets')) ?>">
                <label class="kiezer__label" for="event">Editie</label>
                <select class="kiezer__veld" id="event" name="event">
                    <?php foreach ($evenementen as $optie) : ?>
                        <option
                            value="<?= e($optie->id) ?>"
                            <?= $evenement !== null && $evenement->id === $optie->id ? 'selected' : '' ?>
                        ><?= e($optie->naam) ?> — <?= e(datumNl($optie->startDatum())) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="knop knop--klein" type="submit">Toon tijdsloten</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<section class="sectie">
    <div class="container">
        <?php if ($foutmelding !== null) : ?>
            <?= partial('melding', [
                'soort' => 'fout',
                'kop'   => 'De tickets konden niet worden opgehaald',
                'tekst' => $foutmelding,
                'actieUrl'   => url('/tickets'),
                'actieLabel' => 'Opnieuw proberen',
            ]) ?>
        <?php elseif ($melding !== null) : ?>
            <?= partial('melding', [
                'soort' => 'waarschuwing',
                'kop'   => 'Geen tijdsloten om te tonen',
                'tekst' => $melding,
                'actieUrl'   => url('/events'),
                'actieLabel' => 'Bekijk alle events',
            ]) ?>
        <?php endif; ?>

        <?php if ($evenement !== null && $tijdsloten !== []) : ?>
            <div class="sectie__kop">
                <h2 class="sectie__titel"><?= e($evenement->naam) ?></h2>
                <p class="sectie__meta">
                    <?= e($evenement->locatie) ?> ·
                    <?= e(periodeNl($evenement->startDatum(), $evenement->eindDatum())) ?>
                </p>
            </div>

            <?php foreach ($tijdsloten as $dag => $slotsVanDag) : ?>
                <h3 class="dag-kop"><?= e(datumNl($dag)) ?></h3>
                <div class="raster raster--tijdsloten">
                    <?php foreach ($slotsVanDag as $tijdslot) : ?>
                        <?= partial('tijdslot-kaart', ['tijdslot' => $tijdslot]) ?>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <?= partial('melding', [
                'soort' => 'info',
                'kop'   => 'Online bestellen volgt in Sprint 2',
                'tekst' => 'In deze sprint is de leesfunctionaliteit opgeleverd: je ziet per tijdslot '
                    . 'het tarief en de actuele beschikbaarheid. Het bestelformulier met naam, '
                    . 'e-mailadres en aantal tickets komt in de volgende sprint.',
            ]) ?>
        <?php endif; ?>
    </div>
</section>
