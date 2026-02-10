<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TESTING API ENDPOINT DIRECTLY ===\n\n";

// Simulate API call
use Illuminate\Http\Request;

// Get a Promotion user to simulate authentication
$user = \App\Models\User::where('role', 'Promotion')->first();
if (!$user) {
    echo "ERROR: No Promotion user found\n";
    $user = \App\Models\User::first();
}

echo "Simulating request as: {$user->name} (Role: {$user->role})\n\n";

// Set authenticated user
\Illuminate\Support\Facades\Auth::setUser($user);

// Create controller instance
$controller = new \App\Http\Controllers\Api\Pr\PrPromosiController();

// Create request
$request = Request::create('/api/pr/promosi/works', 'GET');

try {
    // Call controller method
    $response = $controller->index($request);

    $data = json_decode($response->getContent(), true);

    echo "Response Status: " . $response->getStatusCode() . "\n";
    echo "Success: " . ($data['success'] ? 'YES' : 'NO') . "\n";
    echo "Message: " . ($data['message'] ?? 'N/A') . "\n\n";

    if (isset($data['data'])) {
        if (isset($data['data']['data'])) {
            // Paginated response
            $works = $data['data']['data'];
            echo "Total works found: " . count($works) . "\n";
            echo "Current page: " . ($data['data']['current_page'] ?? 'N/A') . "\n";
            echo "Total items: " . ($data['data']['total'] ?? 'N/A') . "\n\n";

            if (count($works) > 0) {
                echo "=== Work Details ===\n";
                foreach ($works as $work) {
                    echo "- Work ID: {$work['id']}\n";
                    echo "  Episode ID: {$work['pr_episode_id']}\n";
                    echo "  Work Type: {$work['work_type']}\n";
                    echo "  Status: {$work['status']}\n";
                    if (isset($work['episode']['title'])) {
                        echo "  Episode Title: {$work['episode']['title']}\n";
                    }
                    echo "\n";
                }
            } else {
                echo "NO WORKS RETURNED!\n";
            }
        } else {
            echo "Unexpected data structure\n";
            print_r($data);
        }
    } else {
        echo "NO DATA in response!\n";
        print_r($data);
    }

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
