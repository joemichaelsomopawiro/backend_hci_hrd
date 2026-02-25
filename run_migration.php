<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    \Illuminate\Support\Facades\Schema::table('pr_programs', function (\Illuminate\Database\Schema\Blueprint $table) {
        $table->date('start_date')->nullable()->after('name');
    });
    echo "Migration successful!\n";
} catch (\Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
