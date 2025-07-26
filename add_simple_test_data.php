<?php
// Script sederhana untuk menambahkan data test
echo "=== ADDING SIMPLE TEST DATA ===\n\n";

// Data test sederhana
$testData = [
    [
        'user_pin' => '1001',
        'user_name' => 'John Doe',
        'card_number' => 'CARD001',
        'date' => '2025-07-01',
        'status' => 'present_ontime',
        'check_in' => '08:00:00',
        'check_out' => '17:00:00',
        'work_hours' => 8.0
    ],
    [
        'user_pin' => '1002',
        'user_name' => 'Jane Smith',
        'card_number' => 'CARD002',
        'date' => '2025-07-01',
        'status' => 'present_late',
        'check_in' => '08:30:00',
        'check_out' => '17:00:00',
        'work_hours' => 7.5
    ],
    [
        'user_pin' => '1003',
        'user_name' => 'Bob Johnson',
        'card_number' => 'CARD003',
        'date' => '2025-07-01',
        'status' => 'absent',
        'check_in' => null,
        'check_out' => null,
        'work_hours' => 0
    ],
    [
        'user_pin' => '1001',
        'user_name' => 'John Doe',
        'card_number' => 'CARD001',
        'date' => '2025-07-02',
        'status' => 'present_ontime',
        'check_in' => '08:00:00',
        'check_out' => '17:00:00',
        'work_hours' => 8.0
    ],
    [
        'user_pin' => '1002',
        'user_name' => 'Jane Smith',
        'card_number' => 'CARD002',
        'date' => '2025-07-02',
        'status' => 'present_ontime',
        'check_in' => '08:00:00',
        'check_out' => '17:00:00',
        'work_hours' => 8.0
    ]
];

// Load Laravel
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Attendance;

echo "Adding test data...\n";

$addedCount = 0;
foreach ($testData as $data) {
    // Check if record already exists
    $existing = Attendance::where('user_pin', $data['user_pin'])
        ->where('date', $data['date'])
        ->first();
    
    if (!$existing) {
        Attendance::create($data);
        $addedCount++;
        echo "Added: {$data['user_name']} - {$data['date']}\n";
    } else {
        echo "Skipped (exists): {$data['user_name']} - {$data['date']}\n";
    }
}

echo "\nAdded $addedCount new records\n";

// Verify
$totalRecords = Attendance::count();
echo "Total attendance records: $totalRecords\n";

$uniqueUserPins = Attendance::distinct('user_pin')->count();
echo "Unique user_pins: $uniqueUserPins\n";

$julyRecords = Attendance::whereYear('date', 2025)->whereMonth('date', 7)->count();
echo "July 2025 records: $julyRecords\n";

echo "\n✅ Test data added successfully!\n"; 