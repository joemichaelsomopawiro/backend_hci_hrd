<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

use App\Models\Program;

$p = Program::where('name', 'LIKE', '%program 53 epsde%')->first();

if ($p) {
    echo "Program Name: {$p->name}\n";
    echo "Category: {$p->category}\n";
    echo "Status: {$p->status}\n";
} else {
    echo "Program not found\n";
}
