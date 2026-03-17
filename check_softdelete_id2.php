<?php
use App\Models\PrManagerDistribusiQcWork;
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$w = PrManagerDistribusiQcWork::withTrashed()->find(2);
if ($w) {
    echo "ID: " . $w->id . "\n";
    echo "Deleted At: " . ($w->deleted_at ? $w->deleted_at->toIso8601String() : 'NULL') . "\n";
} else {
    echo "NOT FOUND EVEN WITH TRASHED\n";
}
