<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$works = \App\Models\PrQualityControlWork::all();
foreach ($works as $work) {
    $checklist = $work->qc_checklist;
    if (is_array($checklist)) {
        $changed = false;
        foreach ($checklist as $k => $v) {
            if (isset($v['status']) && $v['status'] === 'revision') {
                $checklist[$k]['status'] = 'revised';
                $changed = true;
            }
        }
        if ($changed) {
            $work->update(['qc_checklist' => $checklist]);
            echo "Updated work ID: {$work->id}\n";
        }
    }
}
echo "Done.\n";
