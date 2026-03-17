<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$concept = \App\Models\PrProgramConcept::first();
if (!$concept) {
    file_put_contents('test_out.txt', "No concept found.\n");
    exit;
}

$out = "BEFORE:\n";
$out .= "ID: " . $concept->id . "\n";
$out .= "Link: " . $concept->external_link . "\n";

$concept->update(['external_link' => 'https://test.com/newlink']);

$out .= "\nAFTER UPDATE:\n";
$fresh = \App\Models\PrProgramConcept::find($concept->id);
$out .= "ID: " . $fresh->id . "\n";
$out .= "Link: " . $fresh->external_link . "\n";

file_put_contents('test_out.txt', $out);
