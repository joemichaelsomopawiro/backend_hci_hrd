<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Artisan;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Test accessing team members endpoint
echo "=== Testing Team Members Endpoint ===\n\n";

try {
    // Get a program
    $program = \App\Models\PrProgram::first();

    if (!$program) {
        echo "❌ No program found in database\n";
        exit;
    }

    echo "✓ Found program: {$program->id} - {$program->judul_program}\n\n";

    // Get team members
    echo "Getting team members...\n";
    $crews = \App\Models\PrProgramCrew::with('user:id,name,email,role,profile_picture')
        ->where('program_id', $program->id)
        ->get();

    echo "✓ Found {$crews->count()} team members\n\n";

    foreach ($crews as $crew) {
        echo "- ID: {$crew->id}\n";
        echo "  User: {$crew->user->name} ({$crew->user->email})\n";
        echo "  Role: {$crew->role}\n";
        echo "  User Role: {$crew->user->role}\n\n";
    }

    // Test creating a crew member
    echo "\n=== Testing Crew Creation ===\n";

    $testUser = \App\Models\User::where('role', 'Creative')->first();

    if (!$testUser) {
        echo "❌ No Creative user found for testing\n";
    } else {
        echo "✓ Found test user: {$testUser->name} ({$testUser->role})\n";

        // Check if already assigned
        $exists = \App\Models\PrProgramCrew::where('program_id', $program->id)
            ->where('user_id', $testUser->id)
            ->exists();

        if ($exists) {
            echo "ℹ User already assigned to this program\n";
        } else {
            try {
                $newCrew = \App\Models\PrProgramCrew::create([
                    'program_id' => $program->id,
                    'user_id' => $testUser->id,
                    'role' => 'Creative'
                ]);

                echo "✓ Successfully created crew member: {$newCrew->id}\n";

                // Load user details
                $newCrew->load('user:id,name,email,role,profile_picture');
                echo "  User: {$newCrew->user->name}\n";
                echo "  Role in Program: {$newCrew->role}\n";

            } catch (\Exception $e) {
                echo "❌ Error creating crew: {$e->getMessage()}\n";
                echo "Stack trace:\n{$e->getTraceAsString()}\n";
            }
        }
    }

} catch (\Exception $e) {
    echo "❌ ERROR: {$e->getMessage()}\n";
    echo "File: {$e->getFile()}:{$e->getLine()}\n";
    echo "Stack trace:\n{$e->getTraceAsString()}\n";
}
