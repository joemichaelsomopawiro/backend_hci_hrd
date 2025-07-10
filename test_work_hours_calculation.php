<?php

require_once 'vendor/autoload.php';

// Load Laravel environment
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Attendance;
use Carbon\Carbon;

echo "=== Test Perhitungan Jam Kerja dengan Jam Mulai 06:00 ===\n\n";

// Test Case 1: Check-in jam 3 pagi, check-out jam 5 sore
echo "Test Case 1: Check-in jam 03:00, Check-out jam 17:00\n";
$attendance1 = new Attendance();
$attendance1->date = '2025-01-28';
$attendance1->check_in = Carbon::parse('2025-01-28 03:00:00');
$attendance1->check_out = Carbon::parse('2025-01-28 17:00:00');
$workHours1 = $attendance1->calculateWorkHours();
echo "Hasil: {$workHours1} jam (Expected: 11 jam - dari 06:00 sampai 17:00)\n\n";

// Test Case 2: Check-in jam 7 pagi, check-out jam 4 sore
echo "Test Case 2: Check-in jam 07:00, Check-out jam 16:00\n";
$attendance2 = new Attendance();
$attendance2->date = '2025-01-28';
$attendance2->check_in = Carbon::parse('2025-01-28 07:00:00');
$attendance2->check_out = Carbon::parse('2025-01-28 16:00:00');
$workHours2 = $attendance2->calculateWorkHours();
echo "Hasil: {$workHours2} jam (Expected: 9 jam - dari 07:00 sampai 16:00)\n\n";

// Test Case 3: Check-in jam 5 pagi, check-out jam 10 pagi
echo "Test Case 3: Check-in jam 05:00, Check-out jam 10:00\n";
$attendance3 = new Attendance();
$attendance3->date = '2025-01-28';
$attendance3->check_in = Carbon::parse('2025-01-28 05:00:00');
$attendance3->check_out = Carbon::parse('2025-01-28 10:00:00');
$workHours3 = $attendance3->calculateWorkHours();
echo "Hasil: {$workHours3} jam (Expected: 4 jam - dari 06:00 sampai 10:00)\n\n";

// Test Case 4: Check-in jam 6 pagi tepat, check-out jam 3 sore
echo "Test Case 4: Check-in jam 06:00 tepat, Check-out jam 15:00\n";
$attendance4 = new Attendance();
$attendance4->date = '2025-01-28';
$attendance4->check_in = Carbon::parse('2025-01-28 06:00:00');
$attendance4->check_out = Carbon::parse('2025-01-28 15:00:00');
$workHours4 = $attendance4->calculateWorkHours();
echo "Hasil: {$workHours4} jam (Expected: 9 jam - dari 06:00 sampai 15:00)\n\n";

// Test Case 5: Check-in jam 2 pagi, check-out jam 8 pagi
echo "Test Case 5: Check-in jam 02:00, Check-out jam 08:00\n";
$attendance5 = new Attendance();
$attendance5->date = '2025-01-28';
$attendance5->check_in = Carbon::parse('2025-01-28 02:00:00');
$attendance5->check_out = Carbon::parse('2025-01-28 08:00:00');
$workHours5 = $attendance5->calculateWorkHours();
echo "Hasil: {$workHours5} jam (Expected: 2 jam - dari 06:00 sampai 08:00)\n\n";

echo "=== Test Selesai ===\n";
echo "\nCatatan: Jam kerja dimulai dihitung dari jam 06:00 pagi.\n";
echo "Jika check-in sebelum jam 06:00, maka waktu mulai kerja dianggap jam 06:00.\n";
echo "TIDAK ADA pengurangan lunch break - jam kerja murni dari jam 6 pagi sampai check-out.\n";