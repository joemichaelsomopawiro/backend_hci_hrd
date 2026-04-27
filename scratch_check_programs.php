<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$programs = \App\Models\Program::whereIn('name', ['Program Musik 1', 'test'])->get();

foreach ($programs as $p) {
    echo "ID: {$p->id}, Name: {$p->name}, Status: '{$p->status}'\n";
}
