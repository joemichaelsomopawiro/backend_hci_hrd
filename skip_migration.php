<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$migrationName = '2026_03_25_083321_add_soft_deletes_to_users_table';

echo "Syncing migration: $migrationName...\n";

try {
    // Check if migration table exists
    if (!Illuminate\Support\Facades\Schema::hasTable('migrations')) {
        die("ERROR: 'migrations' table not found. Please run 'php artisan migrate' first.\n");
    }

    // Check if already in migrations table
    $exists = DB::table('migrations')->where('migration', $migrationName)->exists();

    if (!$exists) {
        // Find the highest batch number
        $lastBatch = DB::table('migrations')->max('batch') ?? 0;
        $nextBatch = $lastBatch + 1;

        // Insert the migration
        DB::table('migrations')->insert([
            'migration' => $migrationName,
            'batch' => $nextBatch
        ]);
        echo "SUCCESS: Migration '$migrationName' marked as completed in the database.\n";
    } else {
        echo "INFO: Migration '$migrationName' is already marked as completed.\n";
    }

    // Also check for personal_access_tokens table migration if it failed
    $tokensMigration = '2019_12_14_000001_create_personal_access_tokens_table';
    if (Illuminate\Support\Facades\Schema::hasTable('personal_access_tokens')) {
        $tokenExists = DB::table('migrations')->where('migration', $tokensMigration)->exists();
        if (!$tokenExists) {
             DB::table('migrations')->insert([
                'migration' => $tokensMigration,
                'batch' => $nextBatch ?? (DB::table('migrations')->max('batch') + 1)
            ]);
            echo "SUCCESS: Migration '$tokensMigration' marked as completed (since table exists).\n";
        }
    }

    echo "\nNow run: php artisan migrate\n";

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
