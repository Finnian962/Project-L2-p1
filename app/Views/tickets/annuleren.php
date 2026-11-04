<?php

declare(strict_types=1);

/**
 * Bestelling annuleren: eerst zoeken op e-mailadres en ticketnummer, daarna
 * de annulering bevestigen. Na een geslaagde annulering toont dezelfde pagina
 * dat de annulering gelukt is en welk bedrag er terugbetaald wordt.
 *
 * @var array<string, mixed>  $velden
 * @var array<string, mixed>|null $bestelling Gevonden bestelling.
 * @var bool                  $actieMogelijk De bevestigingsknop tonen.
 * @var string|null           $succesmelding
 * @var string|null           $melding       Geen bestelling gevonden.
 * @var string|null           $foutmelding   Validatie- of technische fout.
 * @var string                $foutSoort     waarschuwing of fout.
 */

$terugbetaald = $bestelling === null
    ? null
    : (float) $bestelling['tarief'] * (int) $bestelling['aantal'];
?>
<section class="pagina-kop">
    <div class="container">
        <p class="pagina-kop__label">Tickets</p>
        <h1 class="pagina-kop__titel">Bestelling annuleren</h1>
        <p class="pagina-kop__tekst">
            Vul je e-mailadres en ticketnummer in om je bestelling op te halen. Na je
            bevestiging worden de tickets ongeldig en wordt het betaalde bedrag terugbetaald.
            Annuleren kan tot de start van het evenement.
        </p>

        <div class="pagina-kop__acties">
            <a class="knop knop--leeg" href="<?= e(url('/tickets')) ?>">Terug naar de tijdsloten</a>
            <a class="knop knop--leeg" href="<?= e(url('/tickets/wijzigen')) ?>">Bestelling wijzigen</a>
        </div>
    </div>
</section>

<section class="sectie">
    <div class="container">
        <?php if ($succesmelding !== null) : ?>
            <?= partial('melding', [
                'soort' => 'info',
                'kop'   => 'Annulering verstuurd naar '
                    . ($bestelling['emailadres'] ?? $velden['emailadres']),
                'tekst' => $succesmelding,
            ]) ?>

            <?= partial('melding', [
                'soort' => 'info',
                'kop'   => 'Terugbetaald',
                'tekst' => 'Het bedrag van ' . euro($terugbetaald ?? 0)
                    . ' voor ' . ($bestelling['aantal'] ?? 0)
                    . ((int) ($bestelling['aantal'] ?? 0) === 1 ? ' ticket' : ' tickets')
                    . ' wordt teruggestort op je rekening.',
            ]) ?>
        <?php endif; ?>

        <?php if ($foutmelding !== null) : ?>
            <?= partial('melding', [
                'soort' => $foutSoort,
                'tekst' => $foutmelding,
            ]) ?>
        <?php endif; ?>

        <?php if ($melding !== null) : ?>
            <?= partial('melding', [
                'soort' => 'waarschuwing',
                'tekst' => $melding,
            ]) ?>
        <?php endif; ?>

        <form class="formulier" method="get" action="<?= e(url('/tickets/annuleren')) ?>">
            <div class="formulier__veld">
                <label class="formulier__label" for="email">E-mailadres</label>
                <input
                    class="formulier__invoer"
                    type="email"
                    id="email"
                    name="email"
                    maxlength="150"
                    value="<?= e($velden['emailadres']) ?>"
                    placeholder="jan@voorbeeld.nl"
                    required
                >
                <span class="formulier__hint">
                    Het adres waarmee je de bestelling hebt geplaatst.
                </span>
            </div>

            <div class="formulier__veld">
                <label class="formulier__label" for="ticket">Ticketnummer</label>
                <input
                    class="formulier__invoer"
                    type="number"
                    id="ticket"
                    name="ticket"
                    min="1"
                    value="<?= e($velden['ticketnummer'] > 0 ? $velden['ticketnummer'] : '') ?>"
                    placeholder="123"
                    required
                >
                <span class="formulier__hint">
                    Het nummer uit de bevestiging van je bestelling.
                </span>
            </div>

            <div class="formulier__acties">
                <button class="knop" type="submit">Zoek bestelling</button>
                <a class="knop knop--leeg" href="<?= e(url('/tickets')) ?>">Terug naar de tijdsloten</a>
            </div>
        </form>

        <?php if ($bestelling !== null) : ?>
            <div class="sectie__kop">
                <h2 class="sectie__titel">Je bestelling</h2>
            </div>

            <?= partial('bestelling-details', ['bestelling' => $bestelling]) ?>

            <?php if ($actieMogelijk) : ?>
                <form class="formulier" method="post" action="<?= e(url('/tickets/annuleren')) ?>">
                    <input type="hidden" name="emailadres" value="<?= e($bestelling['emailadres']) ?>">
                    <input type="hidden" name="ticketnummer" value="<?= e($bestelling['ticketnummer']) ?>">

                    <div class="formulier__veld">
                        <span class="formulier__label">Annuleren</span>
                        <p class="formulier__hint">
                            Je bestelling van
                            <?= e($bestelling['aantal']) ?>
                            <?= (int) $bestelling['aantal'] === 1 ? 'ticket' : 'tickets' ?>
                            voor <?= e($bestelling['evenement_naam']) ?>
                            (<?= e(datumNl($bestelling['datum'])) ?>)
                            wordt geannuleerd en de tickets worden ongeldig.
                            Het bedrag van <?= e(euro($terugbetaald ?? 0)) ?> wordt terugbetaald.
                        </p>
                    </div>

                    <div class="formulier__acties">
                        <button class="knop" type="submit">Ja, annuleer mijn bestelling</button>
                        <a
                            class="knop knop--leeg"
                            href="<?= e(url('/tickets')) ?>"
                        >Terug naar de tijdsloten</a>
                    </div>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
