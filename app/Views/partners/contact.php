<?php

declare(strict_types=1);

/**
 * Contactpersonen van één verkoper (voor de organisator).
 *
 * @var int                                $verkoperId
 * @var list<\App\Entities\Contactpersoon> $contactpersonen
 * @var string|null                        $succesmelding
 * @var string|null                        $melding
 * @var string|null                        $foutmelding
 */

use App\Core\Auth;

$verkoperId = $verkoperId ?? 0;
$succesmelding = $succesmelding ?? null;
$verkoperNaam = $contactpersonen === [] ? '' : $contactpersonen[0]->verkoperNaam;
$kanWijzigen = Auth::heeftRol('beheerder');
?>
<section class="pagina-kop">
    <div class="container">
        <p class="kruimelpad"><a href="<?= e(url('/partners')) ?>">Partners</a> / Contact</p>
        <p class="pagina-kop__label">Contactgegevens</p>
        <h1 class="pagina-kop__titel">
            <?= $verkoperNaam === '' ? 'Contactpersonen' : e($verkoperNaam) ?>
        </h1>
        <p class="pagina-kop__tekst">
            De organisatie legt van elke verkoper minimaal één contactpersoon vast, zodat
            wijzigingen in het programma of de opbouwtijden direct doorgegeven kunnen worden.
        </p>
    </div>
</section>

<section class="sectie">
    <div class="container">
        <?php if ($succesmelding !== null) : ?>
            <?= partial('melding', [
                'soort' => 'info',
                'kop'   => 'Contactpersoon gewijzigd',
                'tekst' => $succesmelding,
            ]) ?>
        <?php endif; ?>

        <?php if ($foutmelding !== null) : ?>
            <?= partial('melding', [
                'soort' => 'fout',
                'kop'   => 'De contactpersonen konden niet worden opgehaald',
                'tekst' => $foutmelding,
            ]) ?>
        <?php elseif ($melding !== null) : ?>
            <?= partial('melding', [
                'soort' => 'waarschuwing',
                'kop'   => 'Geen contactpersonen',
                'tekst' => $melding,
                'actieUrl'   => url('/partners'),
                'actieLabel' => 'Terug naar partners',
            ]) ?>
        <?php else : ?>
            <div class="tabel-omhulsel">
                <table class="tabel">
                    <caption class="visueel-verborgen">Contactpersonen van <?= e($verkoperNaam) ?></caption>
                    <thead>
                        <tr>
                            <th scope="col">Naam</th>
                            <th scope="col">Telefoonnummer</th>
                            <th scope="col">E-mailadres</th>
                            <?php if ($kanWijzigen) : ?>
                                <th scope="col">Actie</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contactpersonen as $contactpersoon) : ?>
                            <tr>
                                <th scope="row" data-label="Naam"><?= e($contactpersoon->naam) ?></th>
                                <td data-label="Telefoonnummer">
                                    <a href="<?= e($contactpersoon->telefoonLink()) ?>">
                                        <?= e($contactpersoon->telefoonnummer) ?>
                                    </a>
                                </td>
                                <td data-label="E-mailadres">
                                    <a href="mailto:<?= e($contactpersoon->emailadres) ?>">
                                        <?= e($contactpersoon->emailadres) ?>
                                    </a>
                                </td>
                                <?php if ($kanWijzigen) : ?>
                                    <td data-label="Actie">
                                        <a
                                            class="knop knop--klein knop--leeg"
                                            href="<?= e(url('/verkopers/' . $verkoperId . '/contact/' . $contactpersoon->id . '/wijzigen')) ?>"
                                        >Wijzigen</a>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
