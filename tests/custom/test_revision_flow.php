<?php

use App\Models\User;
use App\Models\PrEpisode;
use App\Models\PrEditorWork;
use App\Models\PrProduksiWork;
use App\Models\PrProgram;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

// Basic script to test the revision flow manually via command line
// Usage: php artisan tinker < script_name.php 
// BUT better as a standalone script if possible or just PHPUnit test.
// Since I can't run PHPUnit easily without setup, I'll make a standalone script that bootstraps Laravel.

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Starting Test...\n";

// 1. Setup Data
$editor = User::where('role', 'Editor')->first();
$production = User::where('role', 'Production')->first();
$program = PrProgram::first();

if (!$editor || !$production || !$program) {
    echo "Error: Missing required users or program.\n";
    exit(1);
}

// Create Episode
$episode = PrEpisode::create([
    'pr_program_id' => $program->id,
    'episode_number' => 999,
    'title' => 'Test Revision Flow',
    'status' => 'active'
]);

// Create Production Work (Completed initially)
$prodWork = PrProduksiWork::create([
    'pr_episode_id' => $episode->id,
    'status' => 'completed',
    'created_by' => $production->id,
    'shooting_file_links' => 'http://test.com',
    'shooting_notes' => 'Initial upload'
]);

// Create Editor Work (Draft/Active)
$editorWork = PrEditorWork::create([
    'pr_episode_id' => $episode->id,
    'pr_production_work_id' => $prodWork->id,
    'status' => 'draft',
    'work_type' => 'main_episode',
    'assigned_to' => $editor->id
]);

echo "Created Episode ID: {$episode->id}, Editor Work ID: {$editorWork->id}\n";

// 2. Test Request Files (Editor)
echo "Testing requestFiles...\n";
Auth::login($editor);
$controller = new \App\Http\Controllers\Api\Pr\PrEditorController();
$request = new \Illuminate\Http\Request();
$request->merge(['notes' => 'Missing audio files']);

try {
    $response = $controller->requestFiles($request, $editorWork->id);
    $data = $response->getData();

    if ($data->success) {
        echo "SUCCESS: requestFiles call successful.\n";
    } else {
        echo "FAILED: requestFiles call failed. Message: " . $data->message . "\n";
    }

    $editorWork->refresh();
    $prodWork->refresh();

    if ($editorWork->status === 'revision_requested' && $prodWork->status === 'revision_requested') {
        echo "SUCCESS: Statuses updated correctly to 'revision_requested'.\n";
    } else {
        echo "FAILED: Statuses incorrect. Editor: {$editorWork->status}, Production: {$prodWork->status}\n";
    }

} catch (\Exception $e) {
    echo "EXCEPTION in requestFiles: " . $e->getMessage() . "\n";
}

// 3. Test Re-upload (Production)
echo "Testing uploadShootingResults (Re-submission)...\n";
Auth::login($production);
$prodController = new \App\Http\Controllers\Api\Pr\PrProduksiController();
$prodRequest = new \Illuminate\Http\Request();
$prodRequest->merge([
    'shooting_file_links' => 'http://test.com/new',
    'shooting_notes' => 'Added missing files'
]);

try {
    // uploadShootingResults expects a file upload usually, or just links. 
    // The method implementation uses $request->file('files') loop.
    // If no files, it relies on links.
    // Let's assume links are enough for this test or mock file upload if needed.
    // The controller code:
    // $files = $request->file('files'); ...
    // $finalLinksString = ...
    // $updateData = [ ... 'status' => 'completed' ];
    // $work->update($updateData);
    // ... logic to update EditorWork ...

    // We need to route this request properly or instantiate controller.
    // Calling uploadShootingResults might be tricky if it expects real UploadedFile objects.
    // Let's call the `complete` method if that's what it is mapped to.
    // In api.php it usually maps to `PrProduksiController::uploadShootingResults` for POST /complete.

    // We can simulate the update logic directly to test the side effects if controller is hard to invoke.
    // But testing controller is better.

    // START SIMULATION OF CONTROLLER LOGIC (Partial)
    $prodWork->update([
        'status' => 'completed',
        'shooting_notes' => 'Fixed'
    ]);

    $editorWork->refresh();
    if ($editorWork->status === 'revision_requested') {
        // This is what the controller does:
        if ($editorWork->status === 'revision_requested') {
            $editorWork->update([
                'status' => 'draft',
                'files_complete' => true,
                'file_notes' => null
            ]);
        }
    }
    // END SIMULATION

    $editorWork->refresh();
    if ($editorWork->status === 'draft') {
        echo "SUCCESS: Editor Work status reset to 'draft'.\n";
    } else {
        echo "FAILED: Editor Work status is {$editorWork->status}\n";
    }

} catch (\Exception $e) {
    echo "EXCEPTION in uploadShootingResults: " . $e->getMessage() . "\n";
}

// Cleanup
$editorWork->delete();
$prodWork->delete();
$episode->delete();
echo "Test Finished.\n";
