<?php

use App\Models\PrEditorWork;
use App\Models\PrProduksiWork;
use App\Models\PrEditorPromosiWork;
use App\Models\PrDesignGrafisWork;
use App\Models\PrPromotionWork;
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$app->make(Kernel::class)->bootstrap();

echo "Fixing missing IDs...\n";

// 1. Fix PrEditorWork
$editorWorks = PrEditorWork::whereNull('pr_production_work_id')->get();
echo "Found " . $editorWorks->count() . " editor works to fix.\n";

foreach ($editorWorks as $ew) {
    if ($ew->pr_episode_id) {
        $pw = PrProduksiWork::where('pr_episode_id', $ew->pr_episode_id)->first();
        if ($pw) {
            $ew->pr_production_work_id = $pw->id;
            $ew->save();
            echo "Linked EditorWork {$ew->id} to ProductionWork {$pw->id}\n";
        }
    }
}

// 2. Fix PrEditorPromosiWork
$epWorks = PrEditorPromosiWork::whereNull('pr_editor_work_id')
    ->orWhereNull('pr_promotion_work_id')
    ->get();
echo "Found " . $epWorks->count() . " editor promosi works to fix.\n";

foreach ($epWorks as $epw) {
    if ($epw->pr_episode_id) {
        // Fix pr_editor_work_id
        if (!$epw->pr_editor_work_id) {
            $ew = PrEditorWork::where('pr_episode_id', $epw->pr_episode_id)->first();
            if ($ew) {
                $epw->pr_editor_work_id = $ew->id;
            }
        }

        // Fix pr_promotion_work_id
        if (!$epw->pr_promotion_work_id) {
            $prw = PrPromotionWork::where('pr_episode_id', $epw->pr_episode_id)->first();
            if ($prw) {
                $epw->pr_promotion_work_id = $prw->id;
            }
        }

        $epw->save();
        echo "Updated EditorPromosiWork {$epw->id}\n";
    }
}

// 3. Fix PrDesignGrafisWork
$dgWorks = PrDesignGrafisWork::whereNull('pr_production_work_id')
    ->orWhereNull('pr_promotion_work_id')
    ->get();
echo "Found " . $dgWorks->count() . " design grafis works to fix.\n";

foreach ($dgWorks as $dgw) {
    if ($dgw->pr_episode_id) {
        // Fix pr_production_work_id
        if (!$dgw->pr_production_work_id) {
            $pw = PrProduksiWork::where('pr_episode_id', $dgw->pr_episode_id)->first();
            if ($pw) {
                $dgw->pr_production_work_id = $pw->id;
            }
        }

        // Fix pr_promotion_work_id
        if (!$dgw->pr_promotion_work_id) {
            $prw = PrPromotionWork::where('pr_episode_id', $dgw->pr_episode_id)->first();
            if ($prw) {
                $dgw->pr_promotion_work_id = $prw->id;
            }
        }

        $dgw->save();
        echo "Updated DesignGrafisWork {$dgw->id}\n";
    }
}

echo "Fix complete.\n";
