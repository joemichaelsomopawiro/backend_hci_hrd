<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AttendanceLog;
use App\Models\Attendance;
use App\Models\EmployeeAttendance;

echo "🔍 Verifikasi Sistem Hanya Proses PIN Terdaftar\n";
echo "==============================================\n\n";

$today = date('Y-m-d');

// Get all registered PINs (main + PIN2)
$mainPins = EmployeeAttendance::where('is_active', true)
    ->pluck('machine_user_id')
    ->toArray();

$pin2List = EmployeeAttendance::where('is_active', true)
    ->get()
    ->map(function ($employee) {
        $rawData = $employee->raw_data['raw_data'] ?? '';
        if (preg_match('/<PIN2>([^<]+)<\/PIN2>/', $rawData, $matches)) {
            return $matches[1];
        }
        return null;
    })
    ->filter()
    ->toArray();

$allRegisteredPins = array_unique(array_merge($mainPins, $pin2List));

echo "📊 Registered PINs Summary:\n";
echo "• Main PINs: " . count($mainPins) . "\n";
echo "• PIN2s: " . count($pin2List) . "\n";
echo "• Total registered: " . count($allRegisteredPins) . "\n\n";

// Check attendance logs untuk hari ini
$todayLogs = AttendanceLog::whereDate('datetime', $today)->get();
echo "📋 Attendance Logs Hari Ini ({$today}):\n";
echo "• Total logs: " . $todayLogs->count() . "\n";

$registeredLogs = $todayLogs->whereIn('user_pin', $allRegisteredPins);
$unregisteredLogs = $todayLogs->whereNotIn('user_pin', $allRegisteredPins);

echo "• Logs dari PIN terdaftar: " . $registeredLogs->count() . "\n";
echo "• Logs dari PIN TIDAK terdaftar: " . $unregisteredLogs->count() . "\n\n";

if ($unregisteredLogs->count() > 0) {
    echo "❌ MASIH ADA PIN TIDAK TERDAFTAR:\n";
    $unregisteredPins = $unregisteredLogs->pluck('user_pin')->unique();
    foreach ($unregisteredPins as $pin) {
        $count = $unregisteredLogs->where('user_pin', $pin)->count();
        echo "   • PIN: {$pin} | Taps: {$count}\n";
    }
    echo "\n";
} else {
    echo "✅ SEMUA LOGS HARI INI DARI PIN TERDAFTAR!\n\n";
}

// Check attendance records
$todayAttendances = Attendance::where('date', $today)->get();
echo "📊 Attendance Records Hari Ini:\n";
echo "• Total attendances: " . $todayAttendances->count() . "\n";

$registeredAttendances = $todayAttendances->whereIn('user_pin', $allRegisteredPins);
$unregisteredAttendances = $todayAttendances->whereNotIn('user_pin', $allRegisteredPins);

echo "• Attendances dari PIN terdaftar: " . $registeredAttendances->count() . "\n";
echo "• Attendances dari PIN TIDAK terdaftar: " . $unregisteredAttendances->count() . "\n\n";

if ($unregisteredAttendances->count() > 0) {
    echo "❌ MASIH ADA ATTENDANCE DARI PIN TIDAK TERDAFTAR:\n";
    foreach ($unregisteredAttendances as $att) {
        echo "   • PIN: {$att->user_pin} | Name: {$att->user_name} | Status: {$att->status}\n";
    }
    echo "\n";
} else {
    echo "✅ SEMUA ATTENDANCES HARI INI DARI PIN TERDAFTAR!\n\n";
}

// Check untuk "User_xxxxx" names
echo "🔍 Checking untuk nama 'User_xxxxx':\n";
$userXNames = $todayAttendances->filter(function ($att) {
    return strpos($att->user_name, 'User_') === 0;
});

if ($userXNames->count() > 0) {
    echo "❌ MASIH ADA NAMA 'User_xxxxx':\n";
    foreach ($userXNames as $att) {
        echo "   • PIN: {$att->user_pin} | Name: {$att->user_name}\n";
    }
} else {
    echo "✅ TIDAK ADA NAMA 'User_xxxxx' - Semua nama sudah real!\n";
}

echo "\n🎯 HASIL VERIFIKASI:\n";
echo "====================\n";

if ($unregisteredLogs->count() === 0 && $unregisteredAttendances->count() === 0 && $userXNames->count() === 0) {
    echo "✅ PERFECT! Sistem sudah hanya fokus pada PIN terdaftar\n";
    echo "✅ Tidak ada lagi User_xxxxx yang tidak jelas\n";
    echo "✅ Semua nama attendance sudah real dari mesin\n";
} else {
    echo "⚠️  Masih ada beberapa data yang perlu dibersihkan\n";
    if ($unregisteredLogs->count() > 0) echo "   - " . $unregisteredLogs->count() . " logs dari PIN tidak terdaftar\n";
    if ($unregisteredAttendances->count() > 0) echo "   - " . $unregisteredAttendances->count() . " attendances dari PIN tidak terdaftar\n";
    if ($userXNames->count() > 0) echo "   - " . $userXNames->count() . " nama 'User_xxxxx'\n";
}

echo "\n📊 Sample Attendance Names Hari Ini:\n";
foreach ($registeredAttendances->take(10) as $att) {
    echo "   • {$att->user_name} (PIN: {$att->user_pin}) - {$att->status}\n";
}
?> 