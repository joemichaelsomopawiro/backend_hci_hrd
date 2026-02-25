$logFile = 'c:/xampp/htdocs/backend_hci/scripts/test_result.log';
file_put_contents($logFile, "Starting Test\n");

// Simulate authentication
$user = App\Models\User::where('role', 'Editor')->first();
if (!$user) {
$user = App\Models\User::create([
'name' => 'Test Editor',
'email' => 'test_editor_' . time() . '@example.com',
'password' => bcrypt('password'),
'role' => 'Editor'
]);
}
Illuminate\Support\Facades\Auth::login($user);

// Create a test episode
$program = App\Models\PrProgram::firstOrCreate(['name' => 'Test Program']);
$episode = App\Models\PrEpisode::create([
'pr_program_id' => $program->id,
'title' => 'Test Episode for Editor Submit',
'episode_number' => rand(1000, 9999),
'status' => 'active',
'workflow_step' => 6
]);

// Create associated works
App\Models\PrProduksiWork::create(['pr_episode_id' => $episode->id, 'status' => 'completed']);
App\Models\PrCreativeWork::create(['pr_episode_id' => $episode->id, 'status' => 'completed']);

// Create Editor Work
$editorWork = App\Models\PrEditorWork::create([
'pr_episode_id' => $episode->id,
'status' => 'editing',
'assigned_to' => $user->id,
'work_type' => 'main_episode'
]);

// Simulate Update Request
$controller = new App\Http\Controllers\Api\Pr\PrEditorController();
$request = new Illuminate\Http\Request();
$request->merge([
'file_path' => 'https://drive.google.com/test-file',
'file_complete' => true
]);

try {
$response = $controller->update($request, $editorWork->id);
$data = $response->getData();

if ($data->success) {
$updatedWork = App\Models\PrEditorWork::find($editorWork->id);
file_put_contents($logFile, "Update Success. New Status: " . $updatedWork->status . "\n", FILE_APPEND);

if ($updatedWork->status === 'completed' && $updatedWork->completed_at !== null) {
file_put_contents($logFile, "PASS: Work correctly marked as completed.\n", FILE_APPEND);
} else {
file_put_contents($logFile, "FAIL: Work status is not completed.\n", FILE_APPEND);
}

} else {
file_put_contents($logFile, "Update Failed: " . $data->message . "\n", FILE_APPEND);
}

} catch (\Exception $e) {
file_put_contents($logFile, "Exception: " . $e->getMessage() . "\n", FILE_APPEND);
}

// Cleanup
$editorWork->delete();
$episode->delete();
try {
$program->delete();
} catch (\Exception $e) {}