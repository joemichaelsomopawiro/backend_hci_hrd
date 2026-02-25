<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PrDesignGrafisWork;
use App\Models\PrProduksiWork;
use App\Models\PrPromotionWork;
use App\Models\PrEditorWork;
use App\Models\PrEditorPromosiWork;
use Illuminate\Support\Facades\DB;

echo "=== Auto-Create Missing Design Grafis Works ===\n\n";

try {
    // Find episodes where both production and promotion are completed
    // but Design Grafis work doesn't exist
    $episodes = DB::table('pr_episodes as e')
        ->join('pr_produksi_works as prod', 'e.id', '=', 'prod.pr_episode_id')
        ->join('pr_promotion_works as promo', 'e.id', '=', 'promo.pr_episode_id')
        ->leftJoin('pr_design_grafis_works as dg', 'e.id', '=', 'dg.pr_episode_id')
        ->where('prod.status', 'completed')
        ->where('promo.status', 'completed')
        ->whereNull('dg.id')
        ->select(
            'e.id as episode_id',
            'e.episode_number',
            'prod.id as prod_id',
            'promo.id as promo_id'
        )
        ->orderBy('e.episode_number')
        ->get();

    if ($episodes->count() == 0) {
        echo "No missing Design Grafis works found. All good!\n";
        exit(0);
    }

    echo "Found {$episodes->count()} episodes missing Design Grafis works:\n";
    foreach ($episodes as $ep) {
        echo "- Episode {$ep->episode_number} (ID: {$ep->episode_id})\n";
    }
    echo "\n";

    // Ask for confirmation
    echo "Do you want to create Design Grafis works for these episodes? (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    $response = trim(strtolower($line));
    fclose($handle);

    if ($response !== 'yes' && $response !== 'y') {
        echo "Cancelled.\n";
        exit(0);
    }

    echo "\nCreating works...\n";
    $created = 0;

    DB::beginTransaction();

    foreach ($episodes as $ep) {
        echo "\nEpisode {$ep->episode_number}:\n";

        // Create Design Grafis Work
        PrDesignGrafisWork::create([
            'pr_episode_id' => $ep->episode_id,
            'pr_production_work_id' => $ep->prod_id,
            'pr_promotion_work_id' => $ep->promo_id,
            'assigned_to' => null,
            'status' => 'pending'
        ]);
        echo "  ✓ Created Design Grafis Work\n";

        // Also create Editor and Editor Promosi works if they don't exist
        if (!PrEditorWork::where('pr_episode_id', $ep->episode_id)->exists()) {
            PrEditorWork::create([
                'pr_episode_id' => $ep->episode_id,
                'pr_production_work_id' => $ep->prod_id,
                'assigned_to' => null,
                'status' => 'pending',
                'files_complete' => false
            ]);
            echo "  ✓ Created Editor Work\n";
        }

        if (!PrEditorPromosiWork::where('pr_episode_id', $ep->episode_id)->exists()) {
            PrEditorPromosiWork::create([
                'pr_episode_id' => $ep->episode_id,
                'pr_promotion_work_id' => $ep->promo_id,
                'assigned_to' => null,
                'status' => 'pending'
            ]);
            echo "  ✓ Created Editor Promosi Work\n";
        }

        $created++;
    }

    DB::commit();

    echo "\n✅ Successfully created works for {$created} episodes!\n";
    echo "\nYou can now refresh the Design Grafis dashboard to see all episodes.\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
