<?php

use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$app->make(Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\PrProgram;
use App\Models\PrEditorWork;
use App\Models\PrProduksiWork;
use App\Models\PrCreativeWork;
use Illuminate\Support\Facades\Auth;

// Simulate authentication
$user = User::where('role', 'Editor')->first();
if (!$user) {
    // Create a dummy editor if none exists
    $user = User::create([
        'name' => 'Test Editor',
        'email' => 'test_editor_' . time() . '@example.com',
        'password' => bcrypt('password'),
        'role' => 'Editor'
    ]);
}
Auth::login($user);

echo "Logged in as: " . $user->name . "\n";

// Create a test episode
$program = PrProgram::firstOrCreate(['name' => 'Test Program']);
$episode = PrEpisode::create([
    'pr_program_id' => $program->id,
    'title' => 'Test Episode for Editor Submit',
    'episode_number' => rand(1000, 9999),
    'status' => 'active',
    'workflow_step' => 6
]);

echo "Created Episode ID: " . $episode->id . "\n";

// Create associated works (needed for the controller to work properly)
PrProduksiWork::create(['pr_episode_id' => $episode->id, 'status' => 'completed']);
PrCreativeWork::create(['pr_episode_id' => $episode->id, 'status' => 'completed']);

// Create Editor Work
$editorWork = PrEditorWork::create([
    'pr_episode_id' => $episode->id,
    'status' => 'editing',
    'assigned_to' => $user->id,
    'work_type' => 'main_episode'
]);

echo "Created Editor Work ID: " . $editorWork->id . "\n";
echo "Initial Status: " . $editorWork->status . "\n";

// Simulate Update Request with File Path
$controller = new \App\Http\Controllers\Api\Pr\PrEditorController();
$request = new \Illuminate\Http\Request();
$request->merge([
    'file_path' => 'https://drive.google.com/test-file',
    'file_complete' => true
]);

echo "Updating work with file path...\n";

try {
    $response = $controller->update($request, $editorWork->id);
    $data = $response->getData();

    if ($data->success) {
        $updatedWork = PrEditorWork::find($editorWork->id);
        echo "Update Success!\n";
        echo "New Status: " . $updatedWork->status . "\n";
        echo "Completed At: " . $updatedWork->completed_at . "\n";

        if ($updatedWork->status === 'completed' && $updatedWork->completed_at !== null) {
            echo "PASS: Work correctly marked as completed.\n";
        } else {
            echo "FAIL: Work status is not completed.\n";
        }

    } else {
        echo "Update Failed: " . $data->message . "\n";
    }

} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

// Cleanup
$editorWork->delete();
$episode->delete();
