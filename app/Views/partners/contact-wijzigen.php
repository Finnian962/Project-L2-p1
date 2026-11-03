<?php

declare(strict_types=1);

/**
 * Formulier om als beheerder een bestaande contactpersoon te wijzigen.
 *
 * Happy flow  : de gewijzigde gegevens worden opgeslagen; daarna volgt een
 *               doorverwijzing naar de contactlijst met de nieuwe gegevens.
 * Unhappy flow: ontbrekende velden of ongeldige gegevens (422), of de
 *               database is plat (503). De melding verschijnt boven het
 *               formulier, de invoer blijft behouden. novalidate staat aan
 *               zodat de server-melding zichtbaar is bij lege velden.
 *
 * @var int                       $verkoperId
 * @var int                       $contactpersoonId
 * @var string                    $verkoperNaam
 * @var array<string, string>     $waarden
 * @var string|null               $foutmelding
 * @var string|null               $foutKop
 * @var array<string, string>     $fouten
 */

$fouten = $fouten ?? [];
$waarden = $waarden ?? [];
$verkoperNaam = $verkoperNaam ?? '';
$lijstUrl = url('/verkopers/' . $verkoperId . '/contact');
$formUrl = url('/verkopers/' . $verkoperId . '/contact/' . $contactpersoonId . '/wijzigen');
?>
<section class="pagina-kop">
    <div class="container container--smal">
        <p class="kruimelpad">
            <a href="<?= e(url('/partners')) ?>">Partners</a> /
            <a href="<?= e($lijstUrl) ?>">Contact</a> / Wijzigen
        </p>
        <p class="pagina-kop__label">Organisator</p>
        <h1 class="pagina-kop__titel">Contactpersoon wijzigen</h1>
        <p class="pagina-kop__tekst">
            <?= $verkoperNaam === ''
                ? 'Pas de naam, het telefoonnummer of het e-mailadres aan. Na het opslaan zie je de nieuwe gegevens in de lijst.'
                : 'Pas de gegevens van de contactpersoon van ' . e($verkoperNaam) . ' aan. Na het opslaan zie je de nieuwe gegevens in de lijst.' ?>
        </p>

        <div class="pagina-kop__acties">
            <a class="knop knop--leeg" href="<?= e($lijstUrl) ?>">Terug naar contactpersonen</a>
        </div>
    </div>
</section>

<section class="sectie">
    <div class="container container--smal">
        <?php if ($foutmelding !== null) : ?>
            <?= partial('melding', [
                'soort' => 'fout',
                'kop'   => $foutKop ?? 'De contactpersoon is niet opgeslagen',
                'tekst' => $foutmelding,
            ]) ?>
        <?php endif; ?>

        <form
            class="formulier"
            id="contactpersoon-wijzigen-formulier"
            method="post"
            action="<?= e($formUrl) ?>"
            novalidate
        >
            <div class="formulier__veld">
                <label class="formulier__label" for="naam">Naam</label>
                <input
                    class="formulier__invoer"
                    type="text"
                    id="naam"
                    name="naam"
                    value="<?= e($waarden['naam'] ?? '') ?>"
                    maxlength="100"
                    placeholder="Bijvoorbeeld: Samir el Idrissi"
                    <?= isset($fouten['naam']) ? 'aria-invalid="true" aria-describedby="naam-fout"' : '' ?>
                >
                <?php if (isset($fouten['naam'])) : ?>
                    <p class="formulier__fout" id="naam-fout" role="alert"><?= e($fouten['naam']) ?></p>
                <?php endif; ?>
            </div>

            <div class="formulier__veld">
                <label class="formulier__label" for="telefoonnummer">Telefoonnummer</label>
                <input
                    class="formulier__invoer"
                    type="text"
                    id="telefoonnummer"
                    name="telefoonnummer"
                    value="<?= e($waarden['telefoonnummer'] ?? '') ?>"
                    maxlength="25"
                    placeholder="Bijvoorbeeld: +31 6 12345678"
                    inputmode="tel"
                    <?= isset($fouten['telefoonnummer']) ? 'aria-invalid="true" aria-describedby="telefoonnummer-fout"' : '' ?>
                >
                <?php if (isset($fouten['telefoonnummer'])) : ?>
                    <p class="formulier__fout" id="telefoonnummer-fout" role="alert"><?= e($fouten['telefoonnummer']) ?></p>
                <?php endif; ?>
            </div>

            <div class="formulier__veld">
                <label class="formulier__label" for="emailadres">E-mailadres</label>
                <input
                    class="formulier__invoer"
                    type="text"
                    id="emailadres"
                    name="emailadres"
                    value="<?= e($waarden['emailadres'] ?? '') ?>"
                    maxlength="150"
                    placeholder="Bijvoorbeeld: naam@voorbeeld.nl"
                    inputmode="email"
                    <?= isset($fouten['emailadres']) ? 'aria-invalid="true" aria-describedby="emailadres-fout"' : '' ?>
                >
                <?php if (isset($fouten['emailadres'])) : ?>
                    <p class="formulier__fout" id="emailadres-fout" role="alert"><?= e($fouten['emailadres']) ?></p>
                <?php endif; ?>
            </div>

            <div class="formulier__acties">
                <button class="knop" type="submit">Opslaan</button>
                <a class="knop knop--leeg" href="<?= e($lijstUrl) ?>">Annuleren</a>
            </div>
        </form>
    </div>
</section>
