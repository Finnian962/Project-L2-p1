<?php

declare(strict_types=1);

/**
 * Formulier waarmee een medewerker een nieuw tijdslot aanmaakt: editie, dag,
 * starttijd en tarief. Het nieuwe tijdslot komt direct op de ticketpagina
 * (/tickets) te staan, zodat er tickets voor verkocht kunnen worden.
 *
 * @var list<\App\Entities\Evenement>  $evenementen
 * @var \App\Entities\Evenement|null   $evenement
 * @var list<\App\Entities\Tijdslot>   $tijdsloten  Bestaande tijdsloten van de editie.
 * @var array{evenement_id: int, datum: string, tijdslot: string, tarief: string, opmerking: string} $velden
 * @var string|null $succesmelding
 * @var string|null $melding        Geen evenementen of onbekende editie.
 * @var string|null $foutmelding    Validatiefout of technische fout.
 * @var string      $foutSoort      waarschuwing (ingevuld) of fout (technisch).
 */
?>
<section class="pagina-kop">
    <div class="container">
        <p class="pagina-kop__label">Medewerker</p>
        <h1 class="pagina-kop__titel">Ticket toevoegen</h1>
        <p class="pagina-kop__tekst">
            Maak een nieuw tijdslot aan: kies de editie, de dag, het starttijdstip en het
            tarief. Het nieuwe tijdslot verschijnt meteen op de ticketpagina en is daar
            te boeken.
        </p>

        <div class="pagina-kop__acties">
            <a class="knop knop--leeg" href="<?= e(url('/tickets')) ?>">Bekijk de tijdsloten</a>
        </div>
    </div>
</section>

<section class="sectie">
    <div class="container">
        <?php if ($succesmelding !== null) : ?>
            <?= partial('melding', [
                'soort' => 'info',
                'tekst' => $succesmelding,
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

        <?php if ($evenementen !== []) : ?>
            <form class="formulier" method="post" action="<?= e(url('/tickets/toevoegen')) ?>">
                <div class="formulier__veld">
                    <label class="formulier__label" for="evenement_id">Editie</label>
                    <select class="formulier__invoer" id="evenement_id" name="evenement_id">
                        <?php foreach ($evenementen as $optie) : ?>
                            <option
                                value="<?= e($optie->id) ?>"
                                <?= $evenement !== null && $evenement->id === $optie->id ? 'selected' : '' ?>
                            ><?= e($optie->naam) ?> — <?= e(periodeNl($optie->startDatum(), $optie->eindDatum())) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="formulier__veld">
                    <label class="formulier__label" for="datum">Dag</label>
                    <input
                        class="formulier__invoer"
                        type="date"
                        id="datum"
                        name="datum"
                        value="<?= e($velden['datum']) ?>"
                        required
                    >
                    <?php if ($evenement !== null) : ?>
                        <span class="formulier__hint">
                            De editie loopt van <?= e(datumNl($evenement->startDatum())) ?>
                            tot en met <?= e(datumNl($evenement->eindDatum())) ?>.
                        </span>
                    <?php endif; ?>
                </div>

                <div class="formulier__veld">
                    <label class="formulier__label" for="tijdslot">Starttijd</label>
                    <input
                        class="formulier__invoer"
                        type="time"
                        id="tijdslot"
                        name="tijdslot"
                        value="<?= e($velden['tijdslot']) ?>"
                        required
                    >
                    <span class="formulier__hint">Bijvoorbeeld 10:00 voor vroege toegang.</span>
                </div>

                <div class="formulier__veld">
                    <label class="formulier__label" for="tarief">Tarief per ticket</label>
                    <input
                        class="formulier__invoer"
                        type="number"
                        id="tarief"
                        name="tarief"
                        min="0.01"
                        max="9999.99"
                        step="0.01"
                        inputmode="decimal"
                        placeholder="24.50"
                        value="<?= e($velden['tarief']) ?>"
                        required
                    >
                    <?php if ($evenement !== null) : ?>
                        <span class="formulier__hint">
                            Het tijdslot krijgt <?= e($evenement->aantalTicketsPerTijdslot) ?>
                            tickets beschikbaar, net als de andere tijdsloten van deze editie.
                        </span>
                    <?php endif; ?>
                </div>

                <div class="formulier__veld">
                    <label class="formulier__label" for="opmerking">Opmerking <span class="formulier__nietVerplicht">(niet verplicht)</span></label>
                    <input
                        class="formulier__invoer"
                        type="text"
                        id="opmerking"
                        name="opmerking"
                        maxlength="255"
                        placeholder="Bijv. Extra vroege toegang"
                        value="<?= e($velden['opmerking']) ?>"
                    >
                </div>

                <div class="formulier__acties">
                    <button class="knop" type="submit">Opslaan</button>
                    <a class="knop knop--leeg" href="<?= e(url('/tickets')) ?>">Annuleren</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>
