<?php

declare(strict_types=1);

use App\Core\Auth;

/**
 * Standpagina voor verkopers: standtypes, prijzen en beschikbaarheid.
 *
 * @var list<\App\Entities\Evenement> $evenementen
 * @var \App\Entities\Evenement|null  $evenement
 * @var list<\App\Entities\Standtype> $standtypes
 * @var list<\App\Entities\Verkoper>  $verkopers
 * @var int                           $vrijeStands
 * @var string|null                   $melding
 * @var string|null                   $foutmelding
 */

/** Wie er ingelogd is (null wanneer niemand ingelogd is). */
$gebruiker = Auth::gebruiker();
?>
    <div class="container">
        <p class="pagina-kop__label">Voor verkopers</p>
        <h1 class="pagina-kop__titel">Huur een stand</h1>
        <p class="pagina-kop__tekst">
            Shops, merken, partners en privéverkopers huren een stand voor één dag of voor het
            hele weekend. Standtype AA+ staat op de beste plek, AA in de hoofdhal en A is de
            instapstand voor privéverkopers.
        </p>

        <?php if ($evenementen !== []) : ?>
            <form class="kiezer" method="get" action="<?= e(url('/stands')) ?>">
                <label class="kiezer__label" for="event">Editie</label>
                <select class="kiezer__veld" id="event" name="event">
                    <?php foreach ($evenementen as $optie) : ?>
                        <option
                            value="<?= e($optie->id) ?>"
                            <?= $evenement !== null && $evenement->id === $optie->id ? 'selected' : '' ?>
                        ><?= e($optie->naam) ?> — <?= e(datumNl($optie->startDatum())) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="knop knop--klein" type="submit">Toon stands</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<section class="sectie">
    <div class="container">
        <?php if ($foutmelding !== null) : ?>
            <?= partial('melding', [
                'soort' => 'fout',
                'kop'   => 'De stands konden niet worden opgehaald',
                'tekst' => $foutmelding,
                'actieUrl'   => url('/stands'),
                'actieLabel' => 'Opnieuw proberen',
            ]) ?>
        <?php elseif ($melding !== null) : ?>
            <?= partial('melding', [
                'soort' => 'waarschuwing',
                'kop'   => 'Geen stands om te tonen',
                'tekst' => $melding,
                'actieUrl'   => url('/events'),
                'actieLabel' => 'Bekijk alle events',
            ]) ?>
        <?php endif; ?>

        <?php if ($evenement !== null && $standtypes !== []) : ?>
            <!-- Content: show stand types when event and stands exist -->
            <div class="sectie__kop">
                <h2 class="sectie__titel"><?= e($evenement->naam) ?></h2>
                <p class="sectie__meta">
                    <?= e($vrijeStands) ?> van de <?= e($evenement->beschikbareStands) ?> stands nog vrij
                </p>
            </div>

            <div class="raster raster--stands">
                <?php foreach ($standtypes as $standtype) : ?>
                    <article class="standkaart<?= $standtype->isUitverkocht() ? ' standkaart--vol' : '' ?>">
                        <p class="standkaart__type"><?= e($standtype->standType) ?></p>
                        <p class="standkaart__prijs">
                            <?= e(euro($standtype->laagstePrijs)) ?>
                            <span>per dag</span>
                        </p>
                        <p class="standkaart__tekst"><?= e($standtype->omschrijving()) ?></p>

                        <ul class="standkaart__lijst">
                            <li>Twee dagen: <?= e(euro($standtype->prijsTweeDagen())) ?></li>
                            <li><?= e($standtype->aantalStands) ?> stands van dit type</li>
                            <li>
                                <?php if ($standtype->isUitverkocht()) : ?>
                                    <span class="badge badge--rood">Volgeboekt</span>
                                <?php else : ?>
                                    <span class="badge badge--groen"><?= e($standtype->vrijeStands) ?> vrij</span>
                                <?php endif; ?>
                            </li>
                        </ul>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if (($gebruiker['rol'] ?? '') === 'standhouder') : ?>
                <?= partial('melding', [
                    'soort'      => 'info',
                    'kop'        => 'Reserveren kan direct',
                    'tekst'      => 'Kies hieronder een standtype met vrije stands en reserveer je '
                        . 'plek in één klik. Je eigen reserveringen staan bij "Mijn reserveringen".',
                    'actieUrl'   => url('/stands/beschikbaar'),
                    'actieLabel' => 'Bekijk beschikbare standen',
                ]) ?>
            <?php elseif ($gebruiker !== null) : ?>
                <?= partial('melding', [
                    'soort'      => 'waarschuwing',
                    'kop'        => 'Reserveren is voor standhouders',
                    'tekst'      => 'Je bent ingelogd als beheerder. Reserveer met een '
                        . 'standhouderaccount, of beheer de stands zelf via "Stands beheren".',
                    'actieUrl'   => url('/beheer/stands'),
                    'actieLabel' => 'Ga naar Stands beheren',
                ]) ?>
            <?php else : ?>
                <?= partial('melding', [
                    'soort'      => 'info',
                    'kop'        => 'Reserveren doe je met een account',
                    'tekst'      => 'Log in met je standhouderaccount om een vrije stand te kiezen '
                        . 'en te reserveren. Nog geen account? Vraag er één aan bij de organisator.',
                    'actieUrl'   => url('/inloggen'),
                    'actieLabel' => 'Inloggen',
                ]) ?>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($verkopers !== []) : ?>
            <h2 class="sectie__titel">Wie staan er al?</h2>
            <div class="raster raster--verkopers">
                <?php foreach ($verkopers as $verkoper) : ?>
                    <?= partial('verkoper-kaart', ['verkoper' => $verkoper, 'toonContactlink' => true]) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
