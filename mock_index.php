<?php
use App\Models\PrManagerDistribusiQcWork;
use App\Models\PrEditorWork;
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$query = PrManagerDistribusiQcWork::with(['episode.program', 'createdBy', 'reviewedBy']);
$works = $query->orderBy('created_at', 'desc')->paginate(15);

echo "TOTAL_PAGINATED: " . $works->total() . "\n";
echo "COUNT_IN_COLLECTION: " . $works->getCollection()->count() . "\n";

foreach ($works->getCollection() as $work) {
    echo "ID:{$work->id}|EpID:{$work->pr_episode_id}|Status:{$work->status}\n";
}
