<?php

declare(strict_types=1);

/**
 * Standenoverzicht van de beheeromgeving: alle stands van een evenement met
 * type, prijs, status en verhuurder.
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
        <p class="pagina-kop__label">Organisator</p>
        <h1 class="pagina-kop__titel">Stands beheren</h1>
        <p class="pagina-kop__tekst">
            Overzicht van alle stands van de gekozen editie, inclusief de status
            (beschikbaar of gereserveerd) en de standhouder die de stand heeft.
        </p>

        <div class="pagina-kop__acties">
            <a class="knop knop--leeg" href="<?= e(url('/beheer')) ?>">Terug naar beheer</a>
        </div>
    </div>
</section>

<section class="sectie">
    <div class="container">
        <?php if ($evenementen !== []) : ?>
            <form class="kiezer" method="get" action="<?= e(url('/beheer/stands')) ?>">
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
                'soort'   => 'fout',
                'kop'     => 'De stands konden niet worden opgehaald',
                'tekst'   => $foutmelding,
                'actieUrl' => url('/beheer/stands'),
                'actieLabel' => 'Opnieuw proberen',
            ]) ?>
        <?php elseif ($melding !== null) : ?>
            <?= partial('melding', [
                'soort' => 'waarschuwing',
                'kop'   => 'Niets te tonen',
                'tekst' => $melding,
            ]) ?>
        <?php endif; ?>

        <?php if ($standen !== []) : ?>
            <div class="sectie__kop">
                <h2 class="sectie__titel"><?= e($evenement?->naam ?? 'Alle stands') ?></h2>
                <p class="sectie__meta"><?= e(count($standen)) ?> stands</p>
            </div>

            <div class="tabel-omhulsel">
                <table class="tabel">
                    <caption class="visueel-verborgen">Stands met type, prijs, status en verhuurder</caption>
                    <thead>
                        <tr>
                            <th scope="col">Nr.</th>
                            <th scope="col">Type</th>
                            <th scope="col">Prijs</th>
                            <th scope="col">Status</th>
                            <th scope="col">Standhouder</th>
                            <th scope="col">Opmerking</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($standen as $stand) : ?>
                            <tr>
                                <th scope="row" data-label="Nr.">#<?= e($stand['id']) ?></th>
                                <td data-label="Type"><?= e($stand['stand_type']) ?></td>
                                <td data-label="Prijs"><?= e(euro($stand['prijs'])) ?></td>
                                <td data-label="Status">
                                    <?php if ((int) $stand['verhuurd_status'] === 1) : ?>
                                        <span class="badge badge--oranje"><?= e($stand['status']) ?></span>
                                    <?php else : ?>
                                        <span class="badge badge--groen"><?= e($stand['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Standhouder">
                                    <?= e($stand['verkoper_naam'] ?? '—') ?>
                                </td>
                                <td data-label="Opmerking"><?= e($stand['opmerking'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
