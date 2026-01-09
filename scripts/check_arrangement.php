<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\MusicArrangement;

$id = $argv[1] ?? 32;
$arr = MusicArrangement::find($id);

if (!$arr) {
    echo "Arrangement with ID {$id} not found\n";
    exit(1);
}

echo "ID: {$arr->id}\n";
echo "file_path: " . ($arr->file_path ?? 'NULL') . "\n";
echo "file_name: " . ($arr->file_name ?? 'NULL') . "\n";
echo "file_size: " . ($arr->file_size ?? 'NULL') . "\n";
echo "mime_type: " . ($arr->mime_type ?? 'NULL') . "\n";

// Also print storage path existence if file_path exists
if ($arr->file_path) {
    $full = storage_path('app/public/' . $arr->file_path);
    echo "expected_public_path: {$full}\n";
    echo file_exists($full) ? "file_exists: true\n" : "file_exists: false\n";
    $fullLocal = storage_path('app/' . $arr->file_path);
    echo "expected_local_path: {$fullLocal}\n";
    echo file_exists($fullLocal) ? "file_exists_local: true\n" : "file_exists_local: false\n";
}

exit(0);
