<?php

declare(strict_types=1);

/**
 * Formulier voor de beheerder om een nieuwe stand toe te voegen.
 *
 * @var list<\App\Entities\Evenement> $evenementen
 * @var array<string, mixed>          $invoer De vorige invoer (na een mislukte poging).
 * @var string|null                   $foutmelding
 */
?>
<section class="pagina-kop">
    <div class="container container--smal">
        <p class="pagina-kop__label">Organisator</p>
        <h1 class="pagina-kop__titel">Stand toevoegen</h1>
        <p class="pagina-kop__tekst">
            Vul de gegevens van de stand in. Na het opslaan staat de stand in het overzicht én in
            het aanbod op de pagina "Stand huren", zodat standhouders hem direct kunnen reserveren.
        </p>

        <div class="pagina-kop__acties">
            <a class="knop knop--leeg" href="<?= e(url('/beheer/stands')) ?>">Terug naar het overzicht</a>
        </div>
    </div>
</section>

<section class="sectie">
    <div class="container container--smal">
        <?php if ($foutmelding !== null) : ?>
            <?= partial('melding', [
                'soort' => 'fout',
                'kop'   => 'De stand is niet opgeslagen',
                'tekst' => $foutmelding,
            ]) ?>
        <?php endif; ?>

        <form class="formulier" method="post" action="<?= e(url('/beheer/stands/toevoegen')) ?>">
            <div class="formulier__veld">
                <label class="formulier__label" for="evenement_id">Editie</label>
                <select class="formulier__invoer" id="evenement_id" name="evenement_id" required>
                    <?php foreach ($evenementen as $optie) : ?>
                        <option
                            value="<?= e($optie->id) ?>"
                            <?= (int) $invoer['evenement_id'] === $optie->id ? 'selected' : '' ?>
                        ><?= e($optie->naam) ?> — <?= e(datumNl($optie->startDatum())) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="formulier__veld">
                <label class="formulier__label" for="stand_type">Standtype</label>
                <select class="formulier__invoer" id="stand_type" name="stand_type" required>
                    <?php foreach (['A', 'AA', 'AA+'] as $type) : ?>
                        <option
                            value="<?= e($type) ?>"
                            <?= strtoupper((string) $invoer['stand_type']) === $type ? 'selected' : '' ?>
                        >Standtype <?= e($type) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="formulier__veld">
                <label class="formulier__label" for="prijs">Prijs per dag (in euro)</label>
                <input
                    class="formulier__invoer"
                    type="text"
                    inputmode="decimal"
                    id="prijs"
                    name="prijs"
                    value="<?= e($invoer['prijs']) ?>"
                    placeholder="495,00"
                    required
                >
            </div>

            <div class="formulier__veld">
                <label class="formulier__label" for="opmerking">Opmerking (optioneel)</label>
                <textarea
                    class="formulier__invoer formulier__invoer--tekst"
                    id="opmerking"
                    name="opmerking"
                    rows="3"
                    maxlength="255"
                    placeholder="Bijvoorbeeld: extra ruimte bij de ingang"
                ><?= e($invoer['opmerking']) ?></textarea>
            </div>

            <div class="formulier__acties">
                <button class="knop" type="submit">Opslaan</button>
                <a class="knop knop--leeg" href="<?= e(url('/beheer/stands')) ?>">Annuleren</a>
            </div>
        </form>
    </div>
</section>
