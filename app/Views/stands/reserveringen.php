<?php

declare(strict_types=1);

/**
 * Overzicht van de reserveringen van de ingelogde standhouder.
 *
 * @var list<array<string, mixed>> $standen
 * @var string|null                $melding
 * @var string|null                $foutmelding
 */
?>
<section class="pagina-kop">
    <div class="container">
        <p class="pagina-kop__label">Voor standhouders</p>
        <h1 class="pagina-kop__titel">Mijn reserveringen</h1>
        <p class="pagina-kop__tekst">
            Alle stands die gekoppeld zijn aan jouw standhouderaccount, met editie, type, prijs
            en de datum waarop de reservering is vastgelegd.
        </p>

        <div class="pagina-kop__acties">
            <a class="knop" href="<?= e(url('/stands/beschikbaar')) ?>">Beschikbare standen</a>
        </div>
    </div>
</section>

<section class="sectie">
    <div class="container">
        <?php if ($foutmelding !== null) : ?>
            <?= partial('melding', [
                'soort'      => 'fout',
                'kop'        => 'De reserveringen konden niet worden opgehaald',
                'tekst'      => $foutmelding,
                'actieUrl'   => url('/reserveringen'),
                'actieLabel' => 'Opnieuw proberen',
            ]) ?>
        <?php elseif ($melding !== null) : ?>
            <?= partial('melding', [
                'soort'      => 'waarschuwing',
                'kop'        => 'Geen reserveringen',
                'tekst'      => $melding,
                'actieUrl'   => url('/stands/beschikbaar'),
                'actieLabel' => 'Bekijk beschikbare standen',
            ]) ?>
        <?php endif; ?>

        <?php if ($standen !== []) : ?>
            <div class="sectie__kop">
                <h2 class="sectie__titel">Gereserveerde stands</h2>
                <p class="sectie__meta"><?= e(count($standen)) ?> reserveringen</p>
            </div>

            <div class="tabel-omhulsel">
                <table class="tabel">
                    <caption class="visueel-verborgen">Reserveringen met editie, type, prijs en status</caption>
                    <thead>
                        <tr>
                            <th scope="col">Nr.</th>
                            <th scope="col">Editie</th>
                            <th scope="col">Type</th>
                            <th scope="col">Prijs</th>
                            <th scope="col">Status</th>
                            <th scope="col">Gereserveerd op</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($standen as $stand) : ?>
                            <tr>
                                <th scope="row" data-label="Nr.">#<?= e($stand['id']) ?></th>
                                <td data-label="Editie">
                                    <?= e($stand['evenement_naam']) ?><br>
                                    <span class="sectie__meta">
                                        <?= e(datumNl((string) $stand['evenement_datum'])) ?> ·
                                        <?= e($stand['evenement_locatie']) ?>
                                    </span>
                                </td>
                                <td data-label="Type"><?= e($stand['stand_type']) ?></td>
                                <td data-label="Prijs"><?= e(euro($stand['prijs'])) ?></td>
                                <td data-label="Status">
                                    <span class="badge badge--oranje"><?= e($stand['status']) ?></span>
                                </td>
                                <td data-label="Gereserveerd op">
                                    <?= e(datumNl((string) substr((string) $stand['datum_aangemaakt'], 0, 10))) ?>
                                    <?php if (!empty($stand['opmerking'])) : ?>
                                        <br><span class="sectie__meta"><?= e($stand['opmerking']) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
