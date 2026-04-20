<?php
require 'vendor/autoload.php';
use App\Models\User;
use App\Services\KpiService;
use Carbon\Carbon;

$userId = 131; // User ID from user_info (NIK matches Quality Control Test)
$user = User::find($userId);
$service = new KpiService();

$month = 4;
$year = 2026;

$results = $service->calculateWorkPoints($user, $month, $year);

echo "Total Points: " . $results['total_points'] . "\n";
echo "Total Tasks: " . $results['breakdown']['total_tasks'] . "\n";

foreach ($results['items'] as $item) {
    if ($item['episode_number'] == 1) {
        echo "---------------------------\n";
        echo "Program: " . $item['program_name'] . "\n";
        echo "Role: " . $item['role'] . "\n";
        echo "Status: " . $item['status'] . "\n";
        echo "Points: " . $item['points'] . "\n";
        echo "Deadline: " . ($item['deadline'] ?? 'N/A') . "\n";
        echo "Completed: " . ($item['completed_time'] ?? 'N/A') . "\n";
    }
}
