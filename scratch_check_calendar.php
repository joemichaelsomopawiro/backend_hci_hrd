<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

use App\Models\Program;
use App\Models\Episode;

$programs = Program::where('category', 'musik')->get(['id', 'name', 'status']);
echo "MUSIC PROGRAMS:\n";
foreach ($programs as $p) {
    echo "- ID: {$p->id}, Name: {$p->name}, Status: [{$p->status}]\n";
}

$year = 2026;
$startDate = "{$year}-01-01 00:00:00";
$endDate = "{$year}-12-31 23:59:59";

$episodes = Episode::whereHas('program', function($q) {
    $q->where('category', 'musik')
      ->whereNotIn('status', ['rejected', 'Rejected', 'REJECTED', 'inactive', 'Inactive', 'non-active', 'nonaktif']);
})
->whereBetween('air_date', [$startDate, $endDate])
->with(['program'])
->get();

echo "\nFILTERED EPISODES COUNT: " . $episodes->count() . "\n";
foreach ($episodes->take(5) as $ep) {
    echo "- Ep ID: {$ep->id}, Program: {$ep->program->name}, Air Date: {$ep->air_date}, Program Status: {$ep->program->status}\n";
}
