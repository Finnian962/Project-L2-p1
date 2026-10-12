<?php

declare(strict_types=1);

/**
 * Pagina met de stands die een standhouder nog kan reserveren.
 *
 * @var list<\App\Entities\Evenement> $evenementen
 * @var \App\Entities\Evenement|null  $evenement
 * @var list<array<string, mixed>>    $standen
 * @var string|null                   $melding
 * @var string|null                   $foutmelding
 */
?>
<section class="pagina-kop">
    <div class="container">
        <p class="pagina-kop__label">Voor standhouders</p>
        <h1 class="pagina-kop__titel">Beschikbare standen</h1>
        <p class="pagina-kop__tekst">
            Kies een stand die nog vrij is en klik op "Reserveren". De stand krijgt daarna de status
            "Gereserveerd" en staat meteen bij jouw reserveringen.
        </p>

        <div class="pagina-kop__acties">
            <a class="knop knop--leeg" href="<?= e(url('/reserveringen')) ?>">Mijn reserveringen</a>
        </div>
    </div>
</section>

<section class="sectie">
    <div class="container">
        <?php if ($evenementen !== []) : ?>
            <form class="kiezer" method="get" action="<?= e(url('/stands/beschikbaar')) ?>">
                <label class="kiezer__label" for="event">Editie</label>
                <select class="kiezer__veld" id="event" name="event">
                    <?php foreach ($evenementen as $optie) : ?>
                        <option
                            value="<?= e($optie->id) ?>"
                            <?= $evenement !== null && $evenement->id === $optie->id ? 'selected' : '' ?>
                        ><?= e($optie->naam) ?> — <?= e(datumNl($optie->startDatum())) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="knop knop--klein" type="submit">Toon stands</button>
            </form>
        <?php endif; ?>

        <?php if ($foutmelding !== null) : ?>
            <?= partial('melding', [
                'soort'      => 'fout',
                'kop'        => 'De stands konden niet worden opgehaald',
                'tekst'      => $foutmelding,
                'actieUrl'   => url('/stands/beschikbaar'),
                'actieLabel' => 'Opnieuw proberen',
            ]) ?>
        <?php elseif ($melding !== null) : ?>
            <?= partial('melding', [
                'soort' => 'waarschuwing',
                'kop'   => 'Niets te reserveren',
                'tekst' => $melding,
            ]) ?>
        <?php endif; ?>

        <?php if ($standen !== []) : ?>
            <div class="sectie__kop">
                <h2 class="sectie__titel"><?= e($evenement?->naam ?? 'Alle edities') ?></h2>
                <p class="sectie__meta"><?= e(count($standen)) ?> stands vrij</p>
            </div>

            <div class="tabel-omhulsel">
                <table class="tabel">
                    <caption class="visueel-verborgen">Beschikbare stands met type, prijs en reserveren</caption>
                    <thead>
                        <tr>
                            <th scope="col">Nr.</th>
                            <th scope="col">Type</th>
                            <th scope="col">Prijs</th>
                            <th scope="col">Locatie</th>
                            <th scope="col">Status</th>
                            <th scope="col"><span class="visueel-verborgen">Actie</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($standen as $stand) : ?>
                            <tr>
                                <th scope="row" data-label="Nr.">#<?= e($stand['id']) ?></th>
                                <td data-label="Type"><?= e($stand['stand_type']) ?></td>
                                <td data-label="Prijs"><?= e(euro($stand['prijs'])) ?></td>
                                <td data-label="Locatie">
                                    <?= e($stand['evenement_locatie']) ?><br>
                                    <span class="sectie__meta"><?= e(datumNl((string) $stand['evenement_datum'])) ?></span>
                                </td>
                                <td data-label="Status">
                                    <span class="badge badge--groen"><?= e($stand['status']) ?></span>
                                </td>
                                <td data-label="Actie">
                                    <form method="post" action="<?= e(url('/stands/reserveren')) ?>">
                                        <input type="hidden" name="stand_id" value="<?= e($stand['id']) ?>">
                                        <input type="hidden" name="evenement_id" value="<?= e($stand['evenement_id']) ?>">
                                        <button class="knop knop--klein" type="submit">Reserveren</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
