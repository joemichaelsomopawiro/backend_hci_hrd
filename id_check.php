<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Episode;
use App\Models\PrEpisode;

$ep = Episode::find(3132);
if ($ep) {
    echo "Found in Episode model (Music)\n";
    echo "Program Name: " . $ep->program->name . "\n";
    // Check if it exists in PrEpisode too (unlikely if primary keys differ)
} else {
    $pep = PrEpisode::find(3132);
    if ($pep) {
        echo "Found in PrEpisode model (Regular)\n";
        echo "Program Name: " . $pep->program->name . "\n";
    } else {
        echo "Not found in either model with ID 3132\n";
    }
}
