<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Program;
use App\Models\Episode;
use Illuminate\Support\Facades\DB;

DB::transaction(function() {
    // 1. Update existing Music Programs that are in 'draft' status to 'approved'
    $affectedPrograms = Program::whereIn('status', ['draft', 'pending_approval'])
        ->where(function($q) {
            $q->where('category', 'musik')
              ->orWhere('category', 'music')
              ->orWhere('category', 'Music Program');
        })
        ->get();

    echo "Found " . $affectedPrograms->count() . " programs to update.\n";

    foreach ($affectedPrograms as $program) {
        echo "Updating Program ID: {$program->id} ({$program->name}) to 'approved'...\n";
        $program->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $program->manager_program_id ?? 1
        ]);

        // 2. Ensure episodes are also in a production-ready status
        $affectedEpisodes = $program->episodes()->where('status', 'draft')->update([
            'status' => 'approved_for_production'
        ]);
        echo "Updated {$affectedEpisodes} episodes for program {$program->id}.\n";
    }
});

echo "Done!\n";
