<?php

declare(strict_types=1);

/**
 * Betaalscherm van de bestelling (gesimuleerde betaalprovider).
 *
 * Er is nog niets opgeslagen: "Betaling afronden" plaatst de bestelling,
 * "Betaling mislukt" laat de bestelling en het ticket achterwege.
 *
 * @var list<\App\Entities\Evenement>  $evenementen
 * @var \App\Entities\Evenement|null   $evenement
 * @var list<\App\Entities\Tijdslot>   $tijdsloten
 * @var array<string, mixed>           $velden
 * @var array<string, mixed>           $bestelling
 * @var string|null $succesmelding
 * @var string|null $melding        Geen evenementen of geen tijdsloten.
 * @var string|null $foutmelding    Validatiefout of technische fout.
 * @var string      $foutSoort      waarschuwing (ingevuld) of fout (technisch).
 */
?>
<section class="pagina-kop">
    <div class="container">
        <p class="pagina-kop__label">Tickets</p>
        <h1 class="pagina-kop__titel">Betaling afronden</h1>
        <p class="pagina-kop__tekst">
            Controleer je bestelling en rond de betaling af. Pas daarna wordt je
            bestelling geplaatst en krijg je een bevestiging met je tickets.
        </p>
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

        <form class="formulier" method="post" action="<?= e(url('/tickets/betalen')) ?>">
            <input type="hidden" name="evenement_id" value="<?= e($bestelling['evenement_id']) ?>">
            <input type="hidden" name="prijs_id" value="<?= e($bestelling['prijs_id']) ?>">
            <input type="hidden" name="aantal" value="<?= e($bestelling['aantal']) ?>">
            <input type="hidden" name="naam" value="<?= e($bestelling['naam']) ?>">
            <input type="hidden" name="emailadres" value="<?= e($bestelling['emailadres']) ?>">

            <div class="formulier__veld">
                <span class="formulier__label">Je bestelling</span>

                <ul class="bestelling">
                    <li class="bestelling__regel">
                        <span>Editie</span>
                        <strong><?= e($bestelling['editie']) ?></strong>
                    </li>

                    <?php if ($bestelling['tarief'] !== null) : ?>
                        <li class="bestelling__regel">
                            <span>Dag en tijdslot</span>
                            <strong>
                                <?= e(datumNl($bestelling['datum'])) ?>
                                — <?= e(tijdKort($bestelling['tijdslot'])) ?>
                            </strong>
                        </li>

                        <li class="bestelling__regel">
                            <span>Tarief per ticket</span>
                            <strong><?= e(euro($bestelling['tarief'])) ?></strong>
                        </li>
                    <?php endif; ?>

                    <li class="bestelling__regel">
                        <span>Aantal tickets</span>
                        <strong><?= e($bestelling['aantal']) ?></strong>
                    </li>

                    <?php if ($bestelling['totaal'] !== null) : ?>
                        <li class="bestelling__regel bestelling__regel--totaal">
                            <span>Totaal</span>
                            <strong><?= e(euro($bestelling['totaal'])) ?></strong>
                        </li>
                    <?php endif; ?>

                    <li class="bestelling__regel">
                        <span>Naam</span>
                        <strong><?= e($bestelling['naam']) ?></strong>
                    </li>

                    <li class="bestelling__regel">
                        <span>E-mailadres</span>
                        <strong><?= e($bestelling['emailadres']) ?></strong>
                    </li>
                </ul>
            </div>

            <div class="formulier__acties">
                <button class="knop" type="submit" name="betaling" value="geslaagd">
                    Betaling afronden
                </button>
                <button class="knop knop--leeg" type="submit" name="betaling" value="mislukt">
                    Betaling mislukt
                </button>
                <a
                    class="knop knop--leeg"
                    href="<?= e(url('/tickets')) ?>?event=<?= e($bestelling['evenement_id']) ?>"
                >Terug naar de tijdsloten</a>
            </div>

            <p class="formulier__hint">
                Demo: de knop "Betaling mislukt" laat zien wat een bezoeker ziet wanneer
                de betaalprovider niet bereikbaar is; er wordt dan niets geplaatst.
            </p>
        </form>
    </div>
</section>
