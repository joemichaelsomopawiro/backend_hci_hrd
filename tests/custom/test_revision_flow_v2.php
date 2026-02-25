<?php

use App\Models\User;
use App\Models\PrEpisode;
use App\Models\PrEditorWork;
use App\Models\PrProduksiWork;
use App\Models\PrProgram;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Starting Test...\n";

try {
    DB::beginTransaction();

    // 1. Setup Data
    echo "Fetching users...\n";
    $editor = User::where('role', 'Editor')->first();
    $production = User::where('role', 'Production')->first();
    $program = PrProgram::first();

    if (!$editor) {
        throw new Exception("Editor not found");
    }
    if (!$production) {
        throw new Exception("Production not found");
    }
    if (!$program) {
        throw new Exception("Program not found");
    }

    echo "Found Editor: {$editor->name}, Production: {$production->name}\n";

    // Create Episode
    $episode = PrEpisode::create([
        'pr_program_id' => $program->id,
        'episode_number' => 999,
        'title' => 'Test Revision Flow ' . time(),
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
        'work_type' => 'main', // Trying 'main' as it is a common default
        'assigned_to' => $editor->id
    ]);
    // NOTE: 'work_type' might be 'editor' or something else. I'll check enum if this fails.
    // Assuming 'editor' or 'main' based on context.
    // Let's check PrEditorWork model casts or DB definition if possible.
    // Proceeding with 'main_editor' or default.

    echo "Created Episode ID: {$episode->id}, Editor Work ID: {$editorWork->id}\n";

    // 2. Test Request Files (Editor)
    echo "Testing requestFiles...\n";
    Auth::login($editor);
    $controller = new \App\Http\Controllers\Api\Pr\PrEditorController();
    $request = new \Illuminate\Http\Request();
    $request->merge(['notes' => 'Missing audio files']);

    $response = $controller->requestFiles($request, $editorWork->id);

    // Check response
    if (method_exists($response, 'getData')) {
        $data = $response->getData();
        if (!$data->success) {
            throw new Exception("requestFiles failed: " . $data->message);
        }
        echo "SUCCESS: requestFiles call successful.\n";
    } else {
        echo "Response is not JsonResponse? " . get_class($response) . "\n";
    }

    $editorWork->refresh();
    $prodWork->refresh();

    if ($editorWork->status === 'revision_requested' && $prodWork->status === 'revision_requested') {
        echo "SUCCESS: Statuses updated correctly to 'revision_requested'.\n";
    } else {
        echo "FAILED: Statuses - Editor: {$editorWork->status}, Production: {$prodWork->status}\n";
    }

    // 3. Test Re-upload (Production)
    echo "Testing uploadShootingResults (Re-submission)...\n";
    Auth::login($production);

    // Simulate re-upload via PrProduksiController logic
    // We instantiate controller to test exact logic
    $prodController = new \App\Http\Controllers\Api\Pr\PrProduksiController();
    $prodRequest = new \Illuminate\Http\Request();

    // Mock request data
    $prodRequest->setMethod('POST');
    $prodRequest->merge([
        'shooting_file_links' => 'http://test.com/new',
        'shooting_notes' => 'Added missing files (Fixed)'
    ]);

    // Check if uploadShootingResults exists and matches signature
    // public function uploadShootingResults(Request $request, $id)
    $prodResponse = $prodController->uploadShootingResults($prodRequest, $prodWork->id);

    if (method_exists($prodResponse, 'getData')) {
        $data = $prodResponse->getData();
        if (!$data->success) {
            // It might return success=false if files are missing but here we provide links
            // Let's see.
            echo "WARNING: uploadShootingResults returned: " . $data->message . "\n";
        } else {
            echo "SUCCESS: uploadShootingResults call successful.\n";
        }
    }

    $editorWork->refresh();
    $prodWork->refresh();

    if ($editorWork->status === 'draft') { // or 'editing' depending on logic
        echo "SUCCESS: Editor Work status reset to '{$editorWork->status}'.\n";
    } else {
        echo "FAILED: Editor Work status is {$editorWork->status} (Expected draft)\n";
    }

    if ($prodWork->status === 'completed') {
        echo "SUCCESS: Production Work status reset to 'completed'.\n";
    } else {
        echo "FAILED: Production Work status is {$prodWork->status}\n";
    }

    // Rollback DB changes for clean cleanup
    DB::rollBack();
    echo "Test Finished (Rolled back changes).\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
