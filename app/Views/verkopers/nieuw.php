<?php

declare(strict_types=1);

/**
 * Formulier om als medewerker een nieuwe verkoper toe te voegen.
 *
 * Happy flow  : het formulier wordt gevuld en verzonden; na succesvol opslaan
 *               volgt een doorverwijzing naar het verkopersoverzicht.
 * Unhappy flow: ongeldige invoer (422) of een databasefout (500). De melding
 *               verschijnt boven het formulier, de ingevulde gegevens blijven
 *               behouden en velden met een fout krijgen een eigen melding.
 *
 * @var string                $titel
 * @var string                $pad
 * @var array<string, string> $waarden
 * @var string|null           $foutmelding
 * @var array<string, string> $fouten
 */

$fouten = $fouten ?? [];

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
                    <?= isset($fouten['naam']) ? 'aria-invalid="true" aria-describedby="naam-fout"' : '' ?>
                >
                <?php if (isset($fouten['naam'])) : ?>
                    <p class="formulier__fout" id="naam-fout" role="alert"><?= e($fouten['naam']) ?></p>
                <?php endif; ?>
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
                <?php if (isset($fouten['status'])) : ?>
                    <p class="formulier__fout" id="status-fout" role="alert"><?= e($fouten['status']) ?></p>
                <?php endif; ?>
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
                    <?= isset($fouten['emailadres']) ? 'aria-invalid="true" aria-describedby="emailadres-fout"' : '' ?>
                >
                <?php if (isset($fouten['emailadres'])) : ?>
                    <p class="formulier__fout" id="emailadres-fout" role="alert"><?= e($fouten['emailadres']) ?></p>
                <?php endif; ?>
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
                    <?= isset($fouten['telefoonnummer']) ? 'aria-invalid="true" aria-describedby="telefoonnummer-fout"' : '' ?>
                >
                <?php if (isset($fouten['telefoonnummer'])) : ?>
                    <p class="formulier__fout" id="telefoonnummer-fout" role="alert"><?= e($fouten['telefoonnummer']) ?></p>
                <?php endif; ?>
            </div>

            <div class="formulier__acties">
                <button class="knop" type="submit">Opslaan</button>
                <a class="knop knop--leeg" href="<?= e(url('/verkopers')) ?>">Annuleren</a>
            </div>
        </form>
    </div>
</section>
