<?php

use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\ProductionTeam;
use App\Http\Controllers\Api\ProductionTeamController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

echo "--- Verifying Producer Team Update Permission ---\n";

try {
    // 1. Find a Program Manager to create the team
    $programManager = User::where('role', 'program_manager')->first();
    if (!$programManager) {
        $programManager = User::factory()->create(['role' => 'program_manager', 'name' => 'Test PM', 'email' => 'pm_test@example.com']);
        echo "Created Test Program Manager: {$programManager->email}\n";
    } else {
        echo "Found Program Manager: {$programManager->email} (ID: {$programManager->id})\n";
    }

    // 2. Find a Producer to try updating the team
    $producer = User::where('role', 'producer')->first();
    if (!$producer) {
        $producer = User::factory()->create(['role' => 'producer', 'name' => 'Test Producer', 'email' => 'producer_test@example.com']);
        echo "Created Test Producer: {$producer->email}\n";
    } else {
        echo "Found Producer: {$producer->email} (ID: {$producer->id})\n";
    }

    // 3. Create a Production Team as Program Manager
    Auth::login($programManager);
    echo "Logged in as Program Manager.\n";

    $teamName = 'Test Team ' . time();
    $team = ProductionTeam::create([
        'name' => $teamName,
        'description' => 'Created by PM for verification',
        'producer_id' => $producer->id, // Assign the producer to it
        'created_by' => $programManager->id,
        'is_active' => true
    ]);

    echo "Created Production Team: '{$team->name}' (ID: {$team->id})\n";
    echo "  - Created By: {$team->created_by}\n";
    echo "  - Producer ID: {$team->producer_id}\n";

    // 4. Switch to Producer and try to update
    Auth::login($producer);
    echo "Switched login to Producer.\n";
    echo "Current User ID: " . Auth::id() . "\n";

    $controller = app(ProductionTeamController::class);
    
    // Attempt update
    $newDescription = 'Updated by Producer ' . time();
    $request = Request::create("/api/production-teams/{$team->id}", 'PUT', [
        'description' => $newDescription
    ]);
    
    // We need to manually setUser for the request if the controller uses $request->user() anywhere internally
    // though ProductionTeamController update doesn't seem to, but good practice.
    $request->setUserResolver(function () use ($producer) {
        return $producer;
    });

    echo "Attempting to update team description...\n";
    
    $response = $controller->update($request, $team->id);
    
    $content = json_decode($response->getContent(), true);
    $status = $response->getStatusCode();

    echo "Response Status: $status\n";
    
    if ($status >= 200 && $status < 300 && $content['success']) {
        echo "SUCCESS: Producer was able to update the team.\n";
        echo "Updated Data: " . json_encode($content['data']) . "\n";
    } elseif ($status === 403) {
        echo "FORBIDDEN: Producer was NOT able to update the team (Authorized).\n";
    } else {
        echo "FAILED: " . ($content['message'] ?? 'Unknown error') . "\n";
        echo "Full Response: " . $response->getContent() . "\n";
    }

    // Cleanup
    $team->delete();
    echo "Cleaned up test team.\n";

} catch (\Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
