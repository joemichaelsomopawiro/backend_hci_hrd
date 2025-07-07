<?php
/**
 * Script untuk debug mapping antara user_pin dan machine_user_id
 */

// Load Laravel
require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Attendance;
use App\Models\EmployeeAttendance;
use Carbon\Carbon;

echo "🔍 DEBUG MAPPING USER_PIN DAN MACHINE_USER_ID\n";
echo "=============================================\n\n";

// Cek data absensi Juli 2025
$attendances = Attendance::whereYear('date', 2025)
    ->whereMonth('date', 7)
    ->get()
    ->groupBy('user_pin');

echo "📊 Data Absensi Juli 2025:\n";
echo "Total unique user_pins: " . $attendances->count() . "\n";
echo "User PINs: " . $attendances->keys()->implode(', ') . "\n\n";

// Cek data employee attendance
$employees = EmployeeAttendance::where('is_active', true)->get();

echo "👥 Data Employee Attendance:\n";
echo "Total active employees: " . $employees->count() . "\n\n";

echo "Mapping Employee:\n";
foreach ($employees as $emp) {
    $nama = $emp->employee ? $emp->employee->nama_lengkap : $emp->name;
    echo "- machine_user_id: {$emp->machine_user_id} -> {$nama}\n";
}

echo "\n";

// Cek overlap
echo "🔍 Cek Overlap:\n";
$userPins = $attendances->keys();
$machineUserIds = $employees->pluck('machine_user_id');

$overlap = $userPins->intersect($machineUserIds);
$onlyInAttendance = $userPins->diff($machineUserIds);
$onlyInEmployee = $machineUserIds->diff($userPins);

echo "✅ Overlap (ada di kedua): " . $overlap->count() . "\n";
if ($overlap->count() > 0) {
    echo "   " . $overlap->implode(', ') . "\n";
}

echo "❌ Hanya di attendance: " . $onlyInAttendance->count() . "\n";
if ($onlyInAttendance->count() > 0) {
    echo "   " . $onlyInAttendance->implode(', ') . "\n";
}

echo "❌ Hanya di employee: " . $onlyInEmployee->count() . "\n";
if ($onlyInEmployee->count() > 0) {
    echo "   " . $onlyInEmployee->implode(', ') . "\n";
}

echo "\n";

// Cek sample data attendance
echo "📋 Sample Data Attendance:\n";
foreach ($attendances->take(5) as $userPin => $data) {
    echo "User PIN: {$userPin}\n";
    foreach ($data->take(3) as $att) {
        echo "  - {$att->date}: {$att->check_in} - {$att->check_out}\n";
    }
    echo "\n";
}

echo "✅ Debug selesai!\n"; 