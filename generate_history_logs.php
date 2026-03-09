<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PrEpisode;
use App\Models\PrActivityLog;

try {
    $episodes = PrEpisode::with([
        'workflowProgress',
        'creativeWork',
        'productionWork',
        'promotionWork',
        'editorWork',
        'editorPromosiWork',
        'designGrafisWork',
        'qualityControlWork',
        'managerDistribusiQcWork'
    ])->get();

    $logService = app(\App\Services\PrActivityLogService::class);

    foreach ($episodes as $episode) {
        if (!$episode->creativeWork && !$episode->productionWork)
            continue;

        // Step 3: Creative (Script/Naskah)
        if ($episode->creativeWork) {
            $cw = $episode->creativeWork;

            $alreadyLoggedSubmit = PrActivityLog::where('episode_id', $episode->id)->where('action', 'submit_creative')->exists();
            if (!$alreadyLoggedSubmit && in_array($cw->status, ['submitted', 'approved'])) {
                $user_id = $cw->created_by ?? 1; // Fallback to 1 if null
                $logService->logEpisodeActivity(
                    $episode,
                    'submit_creative',
                    "Creative script/naskah submitted",
                    ['step' => 3],
                    $user_id
                );
                $log = PrActivityLog::latest()->first();
                $log->created_at = $cw->updated_at;
                $log->save();
            }

            $alreadyLoggedApprove = PrActivityLog::where('episode_id', $episode->id)->where('action', 'approve_creative')->exists();
            if (!$alreadyLoggedApprove && $cw->status === 'approved') {
                $user_id = $cw->reviewed_by ?? 1;
                $logService->logEpisodeActivity(
                    $episode,
                    'approve_creative',
                    "Creative script/naskah approved by Producer",
                    ['step' => 4],
                    $user_id
                );
                $log = PrActivityLog::latest()->first();
                $log->created_at = $cw->reviewed_at ?? $cw->updated_at;
                $log->save();
            }
        }

        // Step 5: Produksi 
        if ($episode->productionWork && $episode->productionWork->status === 'completed') {
            $pw = $episode->productionWork;
            $alreadyLogged = PrActivityLog::where('episode_id', $episode->id)->where('action', 'complete_produksi')->exists();
            if (!$alreadyLogged) {
                $user_id = $pw->completed_by ?? $pw->created_by ?? 1;
                $logService->logEpisodeActivity(
                    $episode,
                    'complete_produksi',
                    "Shooting results uploaded / Produksi completed",
                    ['step' => 5],
                    $user_id
                );
                $log = PrActivityLog::latest()->first();
                $log->created_at = $pw->completed_at ?? $pw->updated_at;
                $log->save();
            }
        }

        // Step 5: Promosi
        if ($episode->promotionWork && $episode->promotionWork->status === 'completed') {
            $pw = $episode->promotionWork;
            $alreadyLogged = PrActivityLog::where('episode_id', $episode->id)->where('action', 'complete_promosi')->exists();
            if (!$alreadyLogged) {
                $user_id = $pw->created_by ?? 1;
                $logService->logEpisodeActivity(
                    $episode,
                    'complete_promosi',
                    "Promotion / BTS Work completed",
                    ['step' => 5],
                    $user_id
                );
                $log = PrActivityLog::latest()->first();
                $log->created_at = $pw->updated_at;
                $log->save();
            }
        }
    }

    echo "Retroactive logs generated successfully.\n";

} catch (\Exception $e) {
    echo "ERROR OCCURRED:\n";
    echo $e->getMessage() . "\n";
    echo $e->getFile() . " on line " . $e->getLine() . "\n";
}
