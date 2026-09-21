<?php

declare(strict_types=1);

/**
 * Beheerpagina met realtime rapportages voor de organisator.
 *
 * @var list<array<string, mixed>> $ticketverkoop
 * @var list<array<string, mixed>> $standverhuur
 * @var string|null                $melding
 * @var string|null                $foutmelding
 */

$totaalTickets = array_sum(array_column($ticketverkoop, 'verkochte_tickets'));
$totaalOmzet = array_sum(array_column($ticketverkoop, 'omzet'))
    + array_sum(array_column($standverhuur, 'omzet'));
?>
<section class="pagina-kop">
    <div class="container">
        <p class="pagina-kop__label">Organisator</p>
        <h1 class="pagina-kop__titel">Beheer en rapportage</h1>
        <p class="pagina-kop__tekst">
            Realtime inzicht in de ticketverkoop en de standverhuur per editie. Het aanmaken,
            wijzigen en verwijderen van events, tijdsloten en stands staat gepland voor Sprint 2.
        </p>

        <div class="pagina-kop__acties">
            <a class="knop knop--leeg" href="<?= e(url('/beheer/log')) ?>">Technische log bekijken</a>
        </div>
    </div>
</section>

<section class="sectie">
    <div class="container">
        <?php if ($foutmelding !== null) : ?>
            <?= partial('melding', [
                'soort' => 'fout',
                'kop'   => 'Het rapport kon niet worden opgehaald',
                'tekst' => $foutmelding,
                'actieUrl'   => url('/beheer'),
                'actieLabel' => 'Opnieuw proberen',
            ]) ?>
        <?php elseif ($melding !== null) : ?>
            <?= partial('melding', [
                'soort' => 'waarschuwing',
                'kop'   => 'Niets te rapporteren',
                'tekst' => $melding,
            ]) ?>
        <?php else : ?>
            <div class="kerncijfers kerncijfers--donker">
                <div class="kerncijfer">
                    <span class="kerncijfer__waarde"><?= e(count($ticketverkoop)) ?></span>
                    <span class="kerncijfer__label">actieve events</span>
                </div>
                <div class="kerncijfer">
                    <span class="kerncijfer__waarde"><?= e($totaalTickets) ?></span>
                    <span class="kerncijfer__label">verkochte tickets</span>
                </div>
                <div class="kerncijfer">
                    <span class="kerncijfer__waarde"><?= e(euro($totaalOmzet)) ?></span>
                    <span class="kerncijfer__label">omzet tickets en stands</span>
                </div>
            </div>

            <h2 class="sectie__titel">Ticketverkoop per event</h2>
            <div class="tabel-omhulsel">
                <table class="tabel">
                    <caption class="visueel-verborgen">Verkochte tickets en omzet per event</caption>
                    <thead>
                        <tr>
                            <th scope="col">Event</th>
                            <th scope="col">Datum</th>
                            <th scope="col">Boekingen</th>
                            <th scope="col">Bezoekers</th>
                            <th scope="col">Tickets</th>
                            <th scope="col">Omzet</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ticketverkoop as $regel) : ?>
                            <tr>
                                <th scope="row">
                                    <a href="<?= e(url('/events/' . $regel['id'])) ?>"><?= e($regel['naam']) ?></a>
                                </th>
                                <td><?= e(datumNl((string) $regel['datum'])) ?></td>
                                <td><?= e($regel['aantal_boekingen']) ?></td>
                                <td><?= e($regel['aantal_bezoekers']) ?></td>
                                <td><?= e($regel['verkochte_tickets']) ?></td>
                                <td><?= e(euro($regel['omzet'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h2 class="sectie__titel">Standverhuur per event</h2>
            <div class="tabel-omhulsel">
                <table class="tabel">
                    <caption class="visueel-verborgen">Verhuurde stands en omzet per event</caption>
                    <thead>
                        <tr>
                            <th scope="col">Event</th>
                            <th scope="col">Stands</th>
                            <th scope="col">Verhuurd</th>
                            <th scope="col">Vrij</th>
                            <th scope="col">Verkopers</th>
                            <th scope="col">Omzet</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($standverhuur as $regel) : ?>
                            <tr>
                                <th scope="row">
                                    <a href="<?= e(url('/events/' . $regel['id'])) ?>"><?= e($regel['naam']) ?></a>
                                </th>
                                <td><?= e($regel['aantal_stands']) ?></td>
                                <td><?= e($regel['verhuurde_stands']) ?></td>
                                <td><?= e($regel['vrije_stands']) ?></td>
                                <td><?= e($regel['aantal_verkopers']) ?></td>
                                <td><?= e(euro($regel['omzet'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
