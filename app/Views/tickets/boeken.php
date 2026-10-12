<?php

declare(strict_types=1);

/**
 * Formulier waarmee een medewerker een ticket boekt op een bestaand tijdslot:
 * editie, tijdslot en aantal. Het opgeslagen ticket telt direct mee in de
 * beschikbaarheid op de ticketpagina (/tickets).
 *
 * @var list<\App\Entities\Evenement>  $evenementen
 * @var \App\Entities\Evenement|null   $evenement
 * @var list<\App\Entities\Tijdslot>   $tijdsloten
 * @var array{evenement_id: int, prijs_id: int, aantal: int} $velden
 * @var string|null $succesmelding
 * @var string|null $melding        Geen evenementen of geen tijdsloten.
 * @var string|null $foutmelding    Validatiefout of technische fout.
 * @var string      $foutSoort      waarschuwing (ingevuld) of fout (technisch).
 */

$gekozenTijdslot = null;

foreach ($tijdsloten as $tijdslot) {
    if ($tijdslot->prijsId === $velden['prijs_id']) {
        $gekozenTijdslot = $tijdslot;
        break;
    }
}

$beschikbaar = $gekozenTijdslot?->beschikbareTickets;
?>
<section class="pagina-kop">
    <div class="container">
        <p class="pagina-kop__label">Medewerker</p>
        <h1 class="pagina-kop__titel">Ticket boeken</h1>
        <p class="pagina-kop__tekst">
            Kies een editie en een tijdslot en geef aan hoeveel tickets je wilt boeken.
            Het ticket wordt direct meegerekend in de beschikbaarheid op de ticketpagina.
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
            <form class="kiezer" method="get" action="<?= e(url('/tickets/boeken')) ?>">
                <label class="kiezer__label" for="event">Editie</label>
                <select class="kiezer__veld" id="event" name="event">
                    <?php foreach ($evenementen as $optie) : ?>
                        <option
                            value="<?= e($optie->id) ?>"
                            <?= $evenement !== null && $evenement->id === $optie->id ? 'selected' : '' ?>
                        ><?= e($optie->naam) ?> — <?= e(datumNl($optie->startDatum())) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="knop knop--klein" type="submit">Toon tijdsloten</button>
            </form>
        <?php endif; ?>

        <?php if ($evenement !== null && $tijdsloten !== []) : ?>
            <form class="formulier" method="post" action="<?= e(url('/tickets/boeken')) ?>">
                <input type="hidden" name="evenement_id" value="<?= e($velden['evenement_id']) ?>">

                <div class="formulier__veld">
                    <label class="formulier__label" for="prijs_id">Tijdslot</label>
                    <select class="formulier__invoer" id="prijs_id" name="prijs_id">
                        <?php foreach ($tijdsloten as $tijdslot) : ?>
                            <option
                                value="<?= e($tijdslot->prijsId) ?>"
                                <?= $velden['prijs_id'] === $tijdslot->prijsId ? 'selected' : '' ?>
                            ><?= e(datumNl($tijdslot->datum)) ?> — <?= e(tijdKort($tijdslot->tijdslot)) ?>
                                · <?= e(euro($tijdslot->tarief)) ?>
                                · <?= e($tijdslot->beschikbareTickets) ?> beschikbaar</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="formulier__veld">
                    <label class="formulier__label" for="aantal">Aantal tickets</label>
                    <input
                        class="formulier__invoer"
                        type="number"
                        id="aantal"
                        name="aantal"
                        min="1"
                        max="<?= e($beschikbaar ?? 1) ?>"
                        value="<?= e($velden['aantal']) ?>"
                        required
                    >
                    <?php if ($beschikbaar !== null) : ?>
                        <span class="formulier__hint">
                            Nog <?= e($beschikbaar) ?> tickets beschikbaar voor dit tijdslot
                            (capaciteit <?= e($gekozenTijdslot?->capaciteit) ?>).
                        </span>
                    <?php endif; ?>
                </div>

                <div class="formulier__acties">
                    <button class="knop" type="submit">Opslaan</button>
                    <a class="knop knop--leeg" href="<?= e(url('/tickets')) ?>">Annuleren</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>
