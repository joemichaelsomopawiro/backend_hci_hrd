<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\PrManagerProgramController;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    // 1. Find the user
    $user = User::where('role', 'Program Manager')->first();
    if (!$user) {
        $user = User::where('role', 'Manager Program')->first();
    }

    if (!$user) {
        $count = User::count();
        $roles = User::select('role')->distinct()->get()->pluck('role');
        echo "No Program Manager found. Total users: $count\n";
        echo "Roles found: " . $roles->implode(', ') . "\n";
        die();
    }

    echo "User found: " . $user->name . " (Role: " . $user->role . ")\n";

    // 2. Login
    Auth::login($user);

    // 3. Prepare request
    $request = Request::create('/api/program-regular/manager-program/programs', 'POST', [
        'name' => 'Test Program Automated',
        'description' => 'Testing program creation',
        'start_date' => date('Y-m-d'),
        'air_time' => '10:00',
        'duration_minutes' => 60,
        'broadcast_channel' => 'Hope Channel',
        'program_year' => 2025
    ]);

    // Simulate Accept: application/json
    $request->headers->set('Accept', 'application/json');

    // 4. Resolve Controller and Call Method directly (bypassing route middleware, but checking controller logic)
    $controller = $app->make(PrManagerProgramController::class);
    $response = $controller->createProgram($request);

    echo "Status Code: " . $response->getStatusCode() . "\n";
    echo "Content: " . $response->getContent() . "\n";

} catch (\Throwable $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
