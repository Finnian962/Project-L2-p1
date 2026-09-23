<?php

declare(strict_types=1);

/**
 * Controleert het verkopersoverzicht: alle verkopers, het soortfilter
 * en de lege lijst die de view als unhappy flow toont.
 *
 * Gebruik: php database/test-verkopers.php
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Models\VerkoperModel;

$model = new VerkoperModel();

$alle     = $model->haalOverzicht();
$sneakers = $model->haalOverzicht(0, 'Sneakers');
$leeg     = $model->haalOverzicht(0, 'Bestaat niet');

echo 'Verkopers totaal : ' . count($alle) . PHP_EOL;
echo 'Filter Sneakers  : ' . count($sneakers) . PHP_EOL;
echo 'Filter onbekend  : ' . count($leeg) . PHP_EOL;

$fouten = [];

if ($alle === []) {
    $fouten[] = 'Geen verkopers gevonden: de happy flow valt terug op een melding.';
}

if (count($sneakers) > count($alle)) {
    $fouten[] = 'Het soortfilter geeft meer rijen dan het volledige overzicht.';
}

foreach ($sneakers as $verkoper) {
    if ($verkoper->verkooptSoort !== 'Sneakers') {
        $fouten[] = 'Filter "Sneakers" levert een verkoper die geen sneakers verkoopt: ' . $verkoper->naam;
        break;
    }
}

if ($leeg !== []) {
    $fouten[] = 'Een onbekend soort moet 0 rijen geven (unhappy flow).';
}

if ($fouten === []) {
    echo PHP_EOL . 'OK: het verkopersoverzicht gedraagt zich zoals verwacht.' . PHP_EOL;
    exit(0);
}

foreach ($fouten as $fout) {
    echo 'FOUT: ' . $fout . PHP_EOL;
}

exit(1);
