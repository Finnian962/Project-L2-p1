<?php

declare(strict_types=1);

/**
 * Bestelformulier voor de bezoeker: editie, tijdslot, aantal tickets, naam en
 * e-mailadres. Na controle volgt het betaalscherm; er wordt hier nog niets
 * opgeslagen.
 *
 * @var list<\App\Entities\Evenement>  $evenementen
 * @var \App\Entities\Evenement|null   $evenement
 * @var list<\App\Entities\Tijdslot>   $tijdsloten
 * @var array<string, mixed>           $velden
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
        <p class="pagina-kop__label">Tickets</p>
        <h1 class="pagina-kop__titel">Tickets bestellen</h1>
        <p class="pagina-kop__tekst">
            Kies een editie en een tijdslot, geef aan hoeveel tickets je wilt hebben
            en vul je naam en e-mailadres in. Je rondt je bestelling daarna af op het
            betaalscherm.
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
            <form class="kiezer" method="get" action="<?= e(url('/tickets/bestellen')) ?>">
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
            <form class="formulier" method="post" action="<?= e(url('/tickets/bestellen')) ?>">
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

                <div class="formulier__veld">
                    <label class="formulier__label" for="naam">Naam</label>
                    <input
                        class="formulier__invoer"
                        type="text"
                        id="naam"
                        name="naam"
                        maxlength="100"
                        value="<?= e($velden['naam']) ?>"
                        placeholder="Jan de Vries"
                        required
                    >
                </div>

                <div class="formulier__veld">
                    <label class="formulier__label" for="emailadres">E-mailadres</label>
                    <input
                        class="formulier__invoer"
                        type="email"
                        id="emailadres"
                        name="emailadres"
                        maxlength="150"
                        value="<?= e($velden['emailadres']) ?>"
                        placeholder="jan@voorbeeld.nl"
                        required
                    >
                    <span class="formulier__hint">
                        Op dit adres ontvang je de bevestiging met je tickets.
                    </span>
                </div>

                <div class="formulier__acties">
                    <button class="knop" type="submit">Bestellen</button>
                    <a class="knop knop--leeg" href="<?= e(url('/tickets')) ?>">Annuleren</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>
