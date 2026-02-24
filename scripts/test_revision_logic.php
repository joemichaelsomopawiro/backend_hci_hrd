<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

use App\Models\User;
use App\Models\PrEpisode;
use App\Models\PrProduksiWork;
use App\Models\PrEditorWork;
use App\Models\PrProgram;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Api\Pr\PrProduksiController;
use Illuminate\Http\Request;

echo "Setting up test data...\n";

// 1. Create Test User
$user = User::where('role', 'Production')->first();
if (!$user) {
    $user = User::factory()->create(['role' => 'Production']);
}
Auth::login($user);
echo "Logged in as: " . $user->name . " (ID: " . $user->id . ")\n";

// 2. Create Test Episode
$program = PrProgram::first() ?? PrProgram::factory()->create();
$episode = PrEpisode::create([
    'pr_program_id' => $program->id,
    'episode_number' => 9999,
    'title' => 'Test Revision Logic',
    'status' => 'in_production',
    'workflow_step' => 4
]);

// 3. Create Production Work
$prodWork = PrProduksiWork::create([
    'pr_episode_id' => $episode->id,
    'status' => 'revision_requested', // Simulate it being sent back? Or 'completed'? 
    // Wait, the USER said: "minta revisi dari produksi, saya sudah buat produksi memperbaikinya dan mengirim nya kembali"
    // So Production status is probably 'revision_requested' or 'in_progress' and they are completing it.
    // Let's request files first.
    'created_by' => $user->id,
    'shooting_file_links' => 'http://test.com', // Needs links to complete
    'shooting_notes' => 'Test notes'
]);

// 4. Create Editor Work (Requests Revision)
$editorWork = PrEditorWork::create([
    'pr_episode_id' => $episode->id,
    'pr_production_work_id' => $prodWork->id,
    'status' => 'revision_requested',
    'file_notes' => 'Missing files',
    'files_complete' => false
]);

echo "Initial Editor Work Status: " . $editorWork->status . "\n";

// 5. Simulate Production Completing Work (Uploading Revision)
// We'll test `completeWork` or `uploadShootingResults`
// The `ProduksiWorkEditor.vue` calls `complete` if files are present.
echo "Simulating Production submitting revision (completeWork)...\n";

$controller = new PrProduksiController();
$request = new Request([
    'completion_notes' => 'Revision done',
    'shooting_file_links' => 'http://new-link.com'
]);

// We need to mock the validation or ensure data is correct.
// completeWork checks shooting_file_links on the model.
$prodWork->update(['shooting_file_links' => 'http://new-link.com']);

try {
    $response = $controller->completeWork($request, $prodWork->id);
    $responseData = $response->getData(true);

    if ($responseData['success']) {
        echo "Controller returned success.\n";
    } else {
        echo "Controller failed: " . $responseData['message'] . "\n";
    }

} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

// 6. Verify Editor Work Status
$editorWork->refresh();
echo "Final Editor Work Status: " . $editorWork->status . "\n";

if ($editorWork->status === 'draft') {
    echo "PASS: Status reset to draft.\n";
} else {
    echo "FAIL: Status is " . $editorWork->status . "\n";
}

// Cleanup
$editorWork->delete();
$prodWork->delete();
$episode->delete();
// $user->delete(); // Keep user
