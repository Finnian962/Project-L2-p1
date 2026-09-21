<?php

declare(strict_types=1);

/**
 * Eventoverzicht (read-functionaliteit Sprint 1).
 *
 * Happy flow   : alle actieve events met naam, datum, locatie en het aantal
 *                beschikbare tickets per tijdslot, gesorteerd op datum.
 * Unhappy flow : melding wanneer er geen events zijn of de database faalt.
 *
 * @var list<\App\Entities\Evenement>            $evenementen
 * @var array<int, list<\App\Entities\Tijdslot>> $tijdslotenPerEvent
 * @var string                                   $zoekterm
 * @var string|null                              $melding
 * @var string|null                              $foutmelding
 */
?>
<section class="pagina-kop">
    <div class="container">
        <p class="pagina-kop__label">Sneakerness® Europa</p>
        <h1 class="pagina-kop__titel">Alle events</h1>
        <p class="pagina-kop__tekst">
            Elk jaar strijkt Sneakerness neer in meerdere Europese steden. Hieronder staan alle
            geplande edities, gesorteerd op datum van vroeg naar laat.
        </p>

        <form class="zoekbalk" method="get" action="<?= e(url('/events')) ?>" role="search">
            <label class="visueel-verborgen" for="zoek">Zoek op naam of stad</label>
            <input
                type="search"
                id="zoek"
                name="zoek"
                value="<?= e($zoekterm) ?>"
                placeholder="Zoek op naam of stad, bijvoorbeeld Rotterdam"
            >
            <button class="knop" type="submit">Zoeken</button>
            <?php if ($zoekterm !== '') : ?>
                <a class="knop knop--leeg" href="<?= e(url('/events')) ?>">Wis filter</a>
            <?php endif; ?>
        </form>
    </div>
</section>

<section class="sectie">
    <div class="container">
        <?php if ($foutmelding !== null) : ?>
            <?= partial('melding', [
                'soort'      => 'fout',
                'kop'        => 'Onze excuses',
                'tekst'      => $foutmelding,
                'actieUrl'   => url('/events'),
                'actieLabel' => 'Opnieuw proberen',
            ]) ?>
        <?php elseif ($melding !== null) : ?>
            <?= partial('melding', [
                'soort' => 'waarschuwing',
                'kop'   => 'Geen events om te tonen',
                'tekst' => $melding,
                'actieUrl'   => $zoekterm === '' ? url('/info') : url('/events'),
                'actieLabel' => $zoekterm === '' ? 'Lees meer over Sneakerness' : 'Toon alle events',
            ]) ?>
        <?php else : ?>
            <p class="resultaat-telling">
                <strong><?= e(count($evenementen)) ?></strong>
                <?= count($evenementen) === 1 ? 'event gevonden' : 'events gevonden' ?>
                <?= $zoekterm !== '' ? ' voor "' . e($zoekterm) . '"' : '' ?>
                · gesorteerd op datum
            </p>

            <div class="raster raster--events">
                <?php foreach ($evenementen as $evenement) : ?>
                    <?= partial('evenement-kaart', [
                        'evenement'  => $evenement,
                        'tijdsloten' => $tijdslotenPerEvent[$evenement->id] ?? [],
                    ]) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
