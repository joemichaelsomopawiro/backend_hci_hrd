<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\EquipmentLoan;

$loan = EquipmentLoan::with(['produksiWork.episode.program'])->find(2);

if (!$loan) {
    echo "Loan ID 2 not found.\n";
    exit;
}

echo "Loan ID: " . $loan->id . "\n";
echo "Created At: " . $loan->created_at . "\n";
echo "Produksi Work ID: " . $loan->pr_produksi_work_id . "\n";

if ($loan->produksiWork) {
    echo "Produksi Work found.\n";
    echo "Episode ID in Work: " . $loan->produksiWork->pr_episode_id . "\n";

    if ($loan->produksiWork->episode) {
        echo "Episode found: " . $loan->produksiWork->episode->title . "\n";
        echo "Program ID in Episode: " . $loan->produksiWork->episode->program_id . "\n";

        if ($loan->produksiWork->episode->program) {
            echo "Program found: " . $loan->produksiWork->episode->program->name . "\n";
        } else {
            echo "Program relation is NULL.\n";
        }
    } else {
        echo "Episode relation is NULL.\n";
    }
} else {
    echo "Produksi Work relation is NULL.\n";
}
