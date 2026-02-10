<?php

use App\Models\User;
use App\Models\PrProgram;
use App\Models\PrEpisode;
use App\Models\PrCreativeWork;
use App\Models\Notification;
use App\Services\PrNotificationService;
use Illuminate\Support\Facades\DB;

// Ensure we have a producer
$producer = User::where('role', 'Producer')->first();
if (!$producer) {
    echo "Creating test producer...\n";
    $producer = User::factory()->create(['role' => 'Producer', 'name' => 'Test Producer']);
}

// Ensure we have a creative
$creative = User::where('role', 'Creative')->first();
if (!$creative) {
    echo "Creating test creative...\n";
    $creative = User::factory()->create(['role' => 'Creative', 'name' => 'Test Creative']);
}

// Create Program & Episode if needed
$program = PrProgram::firstOrCreate(
    ['name' => 'Test Notification Program'],
    ['producer_id' => $producer->id, 'manager_program_id' => $producer->id] // simplified
);

$episode = PrEpisode::firstOrCreate(
    ['program_id' => $program->id, 'episode_number' => 999],
    ['title' => 'Test Episode'] // add other required fields if any
);

// Create Creative Work
$work = PrCreativeWork::firstOrCreate(
    ['pr_episode_id' => $episode->id],
    [
        'created_by' => $creative->id,
        'status' => 'draft',
        'script_content' => 'Test Script',
        'budget_data' => ['test' => 100],
        'shooting_schedule' => ['2023-01-01']
    ]
);

// Clear existing notifications for this work
Notification::where('related_type', 'PrCreativeWork')->where('related_id', $work->id)->delete();

echo "Testing Notification Service...\n";

$service = new PrNotificationService();
$service->notifyCreativeWorkSubmitted($work);

// Verify
$notif = Notification::where('user_id', $producer->id)
    ->where('type', 'pr_creative_work_submitted')
    ->where('related_id', $work->id)
    ->first();

if ($notif) {
    echo "SUCCESS: Notification created for Producer {$producer->name} (ID: {$producer->id})\n";
    echo "Title: {$notif->title}\n";
    echo "Message: {$notif->message}\n";
} else {
    echo "FAILED: Notification not found for Producer {$producer->name} (ID: {$producer->id})\n";
}
