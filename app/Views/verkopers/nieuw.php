<?php

declare(strict_types=1);

/**
 * Formulier om als medewerker een nieuwe verkoper toe te voegen.
 *
 * Happy flow  : het formulier wordt gevuld en verzonden; na succesvol opslaan
 *               volgt een doorverwijzing naar het verkopersoverzicht.
 * Unhappy flow: de database faalt bij het opslaan. De foutmelding verschijnt
 *               boven het formulier en de ingevulde gegevens blijven behouden.
 *
 * @var string               $titel
 * @var string               $pad
 * @var array<string, string> $waarden
 * @var string|null          $foutmelding
 */

/** Statusopties voor de verkoper: gewone verkoper of partner. */
$statussen = [
    '0' => 'Gewone verkoper',
    '1' => 'Partner (speciale status)',
];
?>
<section class="pagina-kop">
    <div class="container">
        <p class="pagina-kop__label">Verkopers</p>
        <h1 class="pagina-kop__titel">Verkoper toevoegen</h1>
        <p class="pagina-kop__tekst">
            Vul de gegevens in van de nieuwe verkoper. Het verkopernummer wordt
            automatisch gegenereerd en de verkoper staat na het opslaan direct
            in het verkopersoverzicht.
        </p>

        <div class="pagina-kop__acties">
            <a class="knop knop--leeg" href="<?= e(url('/verkopers')) ?>">Terug naar het overzicht</a>
        </div>
    </div>
</section>

<section class="sectie">
    <div class="container">
        <?php if ($foutmelding !== null) : ?>
            <?= partial('melding', [
                'soort' => 'fout',
                'kop'   => 'Verkoper niet opgeslagen',
                'tekst' => $foutmelding,
            ]) ?>
        <?php endif; ?>

        <form class="formulier" method="post" action="<?= e(url('/verkopers/nieuw')) ?>">
            <div class="formulier__veld">
                <label class="formulier__label" for="naam">Naam</label>
                <input
                    class="formulier__invoer"
                    type="text"
                    id="naam"
                    name="naam"
                    value="<?= e($waarden['naam']) ?>"
                    maxlength="120"
                    required
                    placeholder="Bijvoorbeeld: Kickz Kollektiv"
                >
            </div>

            <div class="formulier__veld">
                <label class="formulier__label" for="status">Status</label>
                <select class="formulier__invoer" id="status" name="status" required>
                    <?php foreach ($statussen as $waarde => $label) : ?>
                        <option value="<?= e((string) $waarde) ?>"<?= $waarden['status'] === (string) $waarde ? ' selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="formulier__veld">
                <label class="formulier__label" for="emailadres">E-mailadres</label>
                <input
                    class="formulier__invoer"
                    type="email"
                    id="emailadres"
                    name="emailadres"
                    value="<?= e($waarden['emailadres']) ?>"
                    maxlength="150"
                    required
                    placeholder="naam@voorbeeld.nl"
                >
            </div>

            <div class="formulier__veld">
                <label class="formulier__label" for="telefoonnummer">Telefoonnummer</label>
                <input
                    class="formulier__invoer"
                    type="tel"
                    id="telefoonnummer"
                    name="telefoonnummer"
                    value="<?= e($waarden['telefoonnummer']) ?>"
                    maxlength="25"
                    required
                    placeholder="+31 6 12345678"
                >
            </div>

            <div class="formulier__acties">
                <button class="knop" type="submit">Opslaan</button>
                <a class="knop knop--leeg" href="<?= e(url('/verkopers')) ?>">Annuleren</a>
            </div>
        </form>
    </div>
</section>
