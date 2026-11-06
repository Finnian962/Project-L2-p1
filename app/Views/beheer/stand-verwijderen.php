<?php

declare(strict_types=1);

/**
 * Bevestigingsscherm voor het verwijderen van een stand (beheerder).
 *
 * Toont de gegevens van precies die stand voordat de beheerder bevestigt, met
 * de knop Verwijderen en de knop Annuleren. Bestaat de stand niet (meer) of is
 * de database niet bereikbaar, dan toont dezelfde pagina alleen de melding met
 * een weg terug naar het overzicht.
 *
 * @var array<string, mixed>|null $stand       Gegevens van de stand (null bij 404).
 * @var int                       $evenementId Editie van het overzicht.
 * @var string|null               $foutmelding
 * @var string                    $foutKop     Kop boven de foutmelding.
 * @var string                    $foutSoort   info | waarschuwing | fout.
 */

$terug = url('/beheer/stands' . ($evenementId > 0 ? '?event=' . $evenementId : ''));
?>
<section class="pagina-kop">
    <div class="container container--smal">
        <p class="pagina-kop__label">Organisator</p>
        <h1 class="pagina-kop__titel">Stand verwijderen</h1>
        <p class="pagina-kop__tekst">
            Controleer om welke stand het gaat en bevestig daarna het verwijderen. De stand
            verdwijnt uit dit overzicht én uit het aanbod op "Stand huren", zodat standhouders
            hem niet meer kunnen reserveren.
        </p>

        <div class="pagina-kop__acties">
            <a class="knop knop--leeg" href="<?= e($terug) ?>">Terug naar het overzicht</a>
        </div>
    </div>
</section>

<section class="sectie">
    <div class="container container--smal">
        <?php if ($foutmelding !== null) : ?>
            <?= partial('melding', [
                'soort'      => $foutSoort,
                'kop'        => $foutKop,
                'tekst'      => $foutmelding,
                'actieUrl'   => $terug,
                'actieLabel' => 'Terug naar het overzicht',
            ]) ?>
        <?php endif; ?>

        <?php if ($stand !== null) : ?>
            <div class="sectie__kop">
                <h2 class="sectie__titel">Stand #<?= e($stand['id']) ?></h2>
                <p class="sectie__meta">Controleer de gegevens hieronder vóór je verwijdert</p>
            </div>

            <ul class="bestelling">
                <li class="bestelling__regel">
                    <span>Editie</span>
                    <strong>
                        <?= e($stand['evenement_naam']) ?>
                        — <?= e(datumNl($stand['evenement_datum'])) ?>
                    </strong>
                </li>

                <li class="bestelling__regel">
                    <span>Standtype</span>
                    <strong><?= e($stand['stand_type']) ?></strong>
                </li>

                <li class="bestelling__regel">
                    <span>Prijs per dag</span>
                    <strong><?= e(euro($stand['prijs'])) ?></strong>
                </li>

                <li class="bestelling__regel">
                    <span>Status</span>
                    <strong><?= e($stand['status']) ?></strong>
                </li>

                <li class="bestelling__regel">
                    <span>Standhouder</span>
                    <strong><?= e($stand['verkoper_naam'] ?? '—') ?></strong>
                </li>

                <li class="bestelling__regel">
                    <span>Opmerking</span>
                    <strong><?= e($stand['opmerking'] ?? '—') ?></strong>
                </li>
            </ul>

            <form
                class="formulier"
                method="post"
                action="<?= e(url('/beheer/stands/' . (int) $stand['id'] . '/verwijderen')) ?>"
            >
                <input type="hidden" name="event" value="<?= e($evenementId) ?>">

                <div class="formulier__veld">
                    <span class="formulier__label">Bevestigen</span>
                    <p class="formulier__hint">
                        Weet je zeker dat je stand #<?= e($stand['id']) ?>
                        (<?= e($stand['stand_type']) ?>, <?= e(euro($stand['prijs'])) ?>)
                        voor <?= e($stand['evenement_naam']) ?> wilt verwijderen?
                        De stand is daarna niet meer zichtbaar in dit overzicht en niet meer
                        te reserveren. Een bestaande reservering blijft wel bewaard.
                    </p>
                </div>

                <div class="formulier__acties">
                    <button class="knop" type="submit">Verwijderen</button>
                    <a class="knop knop--leeg" href="<?= e($terug) ?>">Annuleren</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>
