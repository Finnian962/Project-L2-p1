<?php

declare(strict_types=1);

/**
 * Bestelling wijzigen: eerst zoeken op e-mailadres en ticketnummer, daarna
 * het aantal tickets aanpassen. Na een geslaagde wijziging toont dezelfde
 * pagina de nieuwe bestelgegevens en het prijsverschil.
 *
 * @var array<string, mixed>  $velden
 * @var array<string, mixed>|null $bestelling Gevonden bestelling.
 * @var bool                  $actieMogelijk Het wijzigformulier tonen.
 * @var int|null              $maximaal      Hoogste aantal voor dit tijdslot.
 * @var string|null           $prijsverschilTekst Bij- of terugbetaling.
 * @var string|null           $succesmelding
 * @var string|null           $melding       Geen bestelling gevonden.
 * @var string|null           $foutmelding   Validatie- of technische fout.
 * @var string                $foutSoort     waarschuwing of fout.
 */
?>
<section class="pagina-kop">
    <div class="container">
        <p class="pagina-kop__label">Tickets</p>
        <h1 class="pagina-kop__titel">Bestelling wijzigen</h1>
        <p class="pagina-kop__tekst">
            Vul je e-mailadres en ticketnummer in om je bestelling op te halen en het aantal
            tickets aan te passen. Het prijsverschil wordt bij- of terugbetaald. Wijzigen kan
            tot de start van het evenement.
        </p>

        <div class="pagina-kop__acties">
            <a class="knop knop--leeg" href="<?= e(url('/tickets')) ?>">Terug naar de tijdsloten</a>
            <a class="knop knop--leeg" href="<?= e(url('/tickets/annuleren')) ?>">Bestelling annuleren</a>
        </div>
    </div>
</section>

<section class="sectie">
    <div class="container">
        <?php if ($succesmelding !== null) : ?>
            <?= partial('melding', [
                'soort' => 'info',
                'kop'   => 'Wijziging verstuurd naar '
                    . ($bestelling['emailadres'] ?? $velden['emailadres']),
                'tekst' => $succesmelding,
            ]) ?>
        <?php endif; ?>

        <?php if ($prijsverschilTekst !== null) : ?>
            <?= partial('melding', [
                'soort' => 'info',
                'kop'   => 'Prijsverschil',
                'tekst' => $prijsverschilTekst,
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

        <form class="formulier" method="get" action="<?= e(url('/tickets/wijzigen')) ?>">
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
                <form class="formulier" method="post" action="<?= e(url('/tickets/wijzigen')) ?>">
                    <input type="hidden" name="emailadres" value="<?= e($bestelling['emailadres']) ?>">
                    <input type="hidden" name="ticketnummer" value="<?= e($bestelling['ticketnummer']) ?>">

                    <div class="formulier__veld">
                        <label class="formulier__label" for="aantal">Nieuw aantal tickets</label>
                        <input
                            class="formulier__invoer"
                            type="number"
                            id="aantal"
                            name="aantal"
                            min="1"
                            <?= $maximaal !== null ? 'max="' . e($maximaal) . '"' : '' ?>
                            value="<?= e($velden['aantal']) ?>"
                            required
                        >
                        <?php if ($maximaal !== null) : ?>
                            <span class="formulier__hint">
                                Maximaal <?= e($maximaal) ?> tickets voor dit tijdslot
                                (je huidige <?= e($bestelling['aantal']) ?> tellen mee).
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="formulier__acties">
                        <button class="knop" type="submit">Wijziging opslaan</button>
                        <a
                            class="knop knop--leeg"
                            href="<?= e(url('/tickets')) ?>"
                        >Terug naar de tijdsloten</a>
                    </div>

                    <p class="formulier__hint">
                        Je ziet na het opslaan meteen de nieuwe bestelgegevens en of er een
                        prijsverschil bij- of terugbetaald wordt.
                    </p>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
