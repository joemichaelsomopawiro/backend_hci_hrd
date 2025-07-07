<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\EmployeeAttendance;

echo "📊 Data Employee Attendance\n";
echo "==========================\n\n";

$users = EmployeeAttendance::where('is_active', true)->orderBy('machine_user_id')->get();
echo "Total users in employee_attendance: " . $users->count() . "\n\n";

if ($users->count() > 0) {
    echo "📋 List users dari mesin:\n";
    foreach($users as $user) {
        $cardDisplay = $user->card_number ?: 'N/A';
        echo "   PIN: {$user->machine_user_id} | Name: {$user->name} | Card: {$cardDisplay}\n";
    }
} else {
    echo "❌ Tidak ada data user di employee_attendance\n";
    echo "💡 Perlu sync user data dari mesin terlebih dahulu\n";
    echo "\n🔧 Cara sync user data:\n";
    echo "   1. Via API: POST /api/attendance/sync/users\n";
    echo "   2. Via dashboard: Klik button sync users\n";
}

echo "\n📍 Sumber nama saat ini:\n";
echo "   1. Priority 1: Database employee_attendance (data real dari mesin)\n";
echo "   2. Priority 2: Mapping manual di kode (fallback)\n";

echo "\n💡 Cara update nama:\n";
echo "   - Sync user data dari mesin untuk mendapat nama real\n";
echo "   - Atau update manual mapping di kode\n";
?> 