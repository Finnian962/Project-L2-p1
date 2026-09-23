<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

use App\Models\EvenementModel;
use App\Models\VerkoperModel;

$em = new EvenementModel();
$vm = new VerkoperModel();

$events = $em->haalOverzicht();
$first = $em->haalEerstvolgende();
$partners = $vm->haalPartners();
$sideStands = $vm->haalSideStands();

echo "Events: " . count($events) . "\n";
echo "First: " . ($first !== null ? 'not null' : 'null') . "\n";
echo "Partners: " . count($partners) . "\n";
echo "SideStands: " . count($sideStands) . "\n";

if ($events === []) {
    echo "evenementen === [] => melding would be set\n";
}