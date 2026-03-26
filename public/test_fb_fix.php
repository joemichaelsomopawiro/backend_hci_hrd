<?php

// Simulate Laravel environment
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PrEditorPromosiWork;
use App\Models\PrEpisode;
use App\Http\Controllers\Api\Pr\PrEditorPromosiController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

echo "--- Testing Facebook Links Array Fix (Robust) ---\n";

DB::beginTransaction();

try {
    // 0. Setup User
    $user = User::where('role', \App\Constants\Role::EDITOR_PROMOTION)->first();
    if (!$user) {
        $user = User::create([
            'name' => 'Test Editor Promosi',
            'email' => 'test_editor_promosi_' . time() . '@example.com',
            'password' => bcrypt('password'),
            'role' => \App\Constants\Role::EDITOR_PROMOTION
        ]);
    }
    Auth::login($user);

    // 1. Create a dummy episode with minimal required fields
    echo "Creating episode...\n";
    $episode = PrEpisode::create([
        'program_id' => 2,
        'episode_number' => 777,
        'title' => 'Test FB Links Robust',
        'status' => 'editing',
        'air_date' => now()->addDays(7)->format('Y-m-d'),
    ]);

    // 2. Create Editor Promosi work
    echo "Creating work...\n";
    $work = PrEditorPromosiWork::create([
        'pr_episode_id' => $episode->id,
        'pr_promotion_work_id' => 1, // Placeholder
        'status' => 'in_progress',
        'bts_video_link' => 'https://example.com/bts',
        'tv_ad_link' => 'https://example.com/ad',
        'ig_highlight_link' => 'https://example.com/ig',
        'tv_highlight_link' => 'https://example.com/tv',
        'fb_highlight_link' => ['https://facebook.com/reel1', 'https://facebook.com/reel2']
    ]);

    echo "Initial state: ID=" . $work->id . ", FB_LINKS_COUNT=" . (is_array($work->fb_highlight_link) ? count($work->fb_highlight_link) : 'NOT ARRAY') . "\n";

    // 3. Trigger submit() via Controller
    echo "Running submit()...\n";
    $controller = app(PrEditorPromosiController::class);
    $response = $controller->submit($work->id);

    $data = $response->getData();
    echo "Response Success: " . ($data->success ? 'YES' : 'NO') . "\n";
    if (!$data->success) {
        echo "Error Message: " . $data->message . "\n";
    }

    $work->refresh();
    echo "Final Status: " . $work->status . "\n";

    if ($data->success && $work->status === 'pending_qc') {
        echo "✅ TEST PASSED: Array of links accepted and status updated.\n";
    } else {
        echo "❌ TEST FAILED!\n";
    }

} catch (\PDOException $e) {
    echo "❌ DB ERROR: " . $e->getMessage() . "\n";
    echo "SQL: " . ($e->errorInfo[2] ?? 'N/A') . "\n";
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " on line " . $e->getLine() . "\n";
} finally {
    DB::rollBack();
}
