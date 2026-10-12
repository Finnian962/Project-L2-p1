<?php

declare(strict_types=1);

/**
 * Formulier om als beheerder een nieuw event toe te voegen.
 *
 * Happy flow  : alle verplichte velden kloppen; na opslaan volgt een
 *               doorverwijzing naar het eventoverzicht.
 * Unhappy flow: ontbrekende velden of ongeldige gegevens (422), of de
 *               database is plat (503). De melding verschijnt boven het
 *               formulier, de invoer blijft behouden. novalidate staat aan
 *               zodat de server-melding zichtbaar is bij lege velden.
 *
 * @var array<string, string> $waarden
 * @var string|null           $foutmelding
 * @var string|null           $foutKop
 * @var array<string, string> $fouten
 */

$fouten = $fouten ?? [];
$waarden = $waarden ?? [];
?>
<section class="pagina-kop">
    <div class="container container--smal">
        <p class="pagina-kop__label">Organisator</p>
        <h1 class="pagina-kop__titel">Event toevoegen</h1>
        <p class="pagina-kop__tekst">
            Vul de gegevens van de nieuwe editie in. Na het opslaan staat het event
            in het eventoverzicht, gesorteerd op datum.
        </p>

        <div class="pagina-kop__acties">
            <a class="knop knop--leeg" href="<?= e(url('/events')) ?>">Terug naar het overzicht</a>
        </div>
    </div>
</section>

<section class="sectie">
    <div class="container container--smal">
        <?php if ($foutmelding !== null) : ?>
            <?= partial('melding', [
                'soort' => 'fout',
                'kop'   => $foutKop ?? 'Het event is niet opgeslagen',
                'tekst' => $foutmelding,
            ]) ?>
        <?php endif; ?>

        <form
            class="formulier"
            id="event-toevoegen-formulier"
            method="post"
            action="<?= e(url('/beheer/events/toevoegen')) ?>"
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
                    maxlength="150"
                    placeholder="Bijvoorbeeld: Sneakerness Rotterdam 2027"
                    <?= isset($fouten['naam']) ? 'aria-invalid="true" aria-describedby="naam-fout"' : '' ?>
                >
                <?php if (isset($fouten['naam'])) : ?>
                    <p class="formulier__fout" id="naam-fout" role="alert"><?= e($fouten['naam']) ?></p>
                <?php endif; ?>
            </div>

            <div class="formulier__veld">
                <label class="formulier__label" for="datum">Datum</label>
                <input
                    class="formulier__invoer"
                    type="text"
                    id="datum"
                    name="datum"
                    value="<?= e($waarden['datum'] ?? '') ?>"
                    placeholder="jjjj-mm-dd, bijvoorbeeld 2026-11-14"
                    inputmode="numeric"
                    <?= isset($fouten['datum']) ? 'aria-invalid="true" aria-describedby="datum-fout"' : '' ?>
                >
                <?php if (isset($fouten['datum'])) : ?>
                    <p class="formulier__fout" id="datum-fout" role="alert"><?= e($fouten['datum']) ?></p>
                <?php endif; ?>
            </div>

            <div class="formulier__veld">
                <label class="formulier__label" for="locatie">Locatie</label>
                <input
                    class="formulier__invoer"
                    type="text"
                    id="locatie"
                    name="locatie"
                    value="<?= e($waarden['locatie'] ?? '') ?>"
                    maxlength="150"
                    placeholder="Bijvoorbeeld: Van Nellefabriek, Rotterdam"
                    <?= isset($fouten['locatie']) ? 'aria-invalid="true" aria-describedby="locatie-fout"' : '' ?>
                >
                <?php if (isset($fouten['locatie'])) : ?>
                    <p class="formulier__fout" id="locatie-fout" role="alert"><?= e($fouten['locatie']) ?></p>
                <?php endif; ?>
            </div>

            <div class="formulier__rij formulier__rij--twee">
                <div class="formulier__veld">
                    <label class="formulier__label" for="aantal_tickets_per_tijdslot">Tickets per tijdslot</label>
                    <input
                        class="formulier__invoer"
                        type="text"
                        inputmode="numeric"
                        id="aantal_tickets_per_tijdslot"
                        name="aantal_tickets_per_tijdslot"
                        value="<?= e($waarden['aantal_tickets_per_tijdslot'] ?? '') ?>"
                        placeholder="250"
                        <?= isset($fouten['aantal_tickets_per_tijdslot'])
                            ? 'aria-invalid="true" aria-describedby="tickets-fout"'
                            : '' ?>
                    >
                    <?php if (isset($fouten['aantal_tickets_per_tijdslot'])) : ?>
                        <p class="formulier__fout" id="tickets-fout" role="alert"><?= e($fouten['aantal_tickets_per_tijdslot']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="formulier__veld">
                    <label class="formulier__label" for="beschikbare_stands">Beschikbare stands</label>
                    <input
                        class="formulier__invoer"
                        type="text"
                        inputmode="numeric"
                        id="beschikbare_stands"
                        name="beschikbare_stands"
                        value="<?= e($waarden['beschikbare_stands'] ?? '') ?>"
                        placeholder="30"
                        <?= isset($fouten['beschikbare_stands'])
                            ? 'aria-invalid="true" aria-describedby="stands-fout"'
                            : '' ?>
                    >
                    <?php if (isset($fouten['beschikbare_stands'])) : ?>
                        <p class="formulier__fout" id="stands-fout" role="alert"><?= e($fouten['beschikbare_stands']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="formulier__veld">
                <label class="formulier__label" for="opmerking">Opmerking (niet verplicht)</label>
                <textarea
                    class="formulier__invoer formulier__invoer--tekst"
                    id="opmerking"
                    name="opmerking"
                    rows="3"
                    maxlength="255"
                    placeholder="Bijvoorbeeld: tweedaagse editie in de Van Nellefabriek"
                    <?= isset($fouten['opmerking']) ? 'aria-invalid="true" aria-describedby="opmerking-fout"' : '' ?>
                ><?= e($waarden['opmerking'] ?? '') ?></textarea>
                <?php if (isset($fouten['opmerking'])) : ?>
                    <p class="formulier__fout" id="opmerking-fout" role="alert"><?= e($fouten['opmerking']) ?></p>
                <?php endif; ?>
            </div>

            <div class="formulier__acties">
                <button class="knop" type="submit">Opslaan</button>
                <a class="knop knop--leeg" href="<?= e(url('/events')) ?>">Annuleren</a>
            </div>
        </form>
    </div>
</section>
