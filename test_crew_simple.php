<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Testing PrProgramCrew query...\n\n";

try {
    // Test simple query
    $crews = \App\Models\PrProgramCrew::where('program_id', 6)->get();
    echo "✓ Found {$crews->count()} crew members for program 6\n";

    // Test with user relation
    $crews = \App\Models\PrProgramCrew::with([
        'user' => function ($query) {
            $query->select('id', 'name', 'email', 'role');
        }
    ])->where('program_id', 6)->get();

    echo "✓ Successfully loaded with user relation\n";

    foreach ($crews as $crew) {
        echo "  - {$crew->user->name} ({$crew->role})\n";
    }

} catch (\Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
    echo "File: {$e->getFile()}:{$e->getLine()}\n";
}
