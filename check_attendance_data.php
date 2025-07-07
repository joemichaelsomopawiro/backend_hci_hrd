<?php
/**
 * Script untuk mengecek data absensi di database
 */

// Load Laravel
require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Attendance;
use App\Models\EmployeeAttendance;
use Carbon\Carbon;

echo "🔍 MENGE CEK DATA ABSENSI DI DATABASE\n";
echo "=====================================\n\n";

// Cek data absensi hari ini
$today = now()->format('Y-m-d');
echo "📅 Data Absensi Hari Ini ({$today}):\n";
$todayAttendances = Attendance::where('date', $today)->get();
echo "Total: " . $todayAttendances->count() . " records\n";

if ($todayAttendances->count() > 0) {
    echo "Sample data:\n";
    foreach ($todayAttendances->take(5) as $att) {
        echo "- {$att->user_pin}: {$att->check_in} - {$att->check_out}\n";
    }
} else {
    echo "❌ Tidak ada data absensi hari ini!\n";
}

echo "\n";

// Cek data absensi bulan ini
$currentMonth = now()->format('Y-m');
echo "📊 Data Absensi Bulan Ini ({$currentMonth}):\n";
$monthAttendances = Attendance::whereYear('date', now()->year)
    ->whereMonth('date', now()->month)
    ->get();
echo "Total: " . $monthAttendances->count() . " records\n";

if ($monthAttendances->count() > 0) {
    echo "Per tanggal:\n";
    $groupedByDate = $monthAttendances->groupBy('date');
    foreach ($groupedByDate as $date => $attendances) {
        echo "- {$date}: " . $attendances->count() . " records\n";
    }
} else {
    echo "❌ Tidak ada data absensi bulan ini!\n";
}

echo "\n";

// Cek data employee attendance
echo "👥 Data Employee Attendance:\n";
$employeeAttendances = EmployeeAttendance::where('is_active', true)->get();
echo "Total active employees: " . $employeeAttendances->count() . "\n";

if ($employeeAttendances->count() > 0) {
    echo "Sample employees:\n";
    foreach ($employeeAttendances->take(5) as $emp) {
        $nama = $emp->employee ? $emp->employee->nama_lengkap : $emp->name;
        echo "- {$emp->machine_user_id}: {$nama}\n";
    }
} else {
    echo "❌ Tidak ada data employee attendance!\n";
}

echo "\n";

// Cek working days untuk bulan ini
echo "📅 Hari Kerja Bulan Ini:\n";
$year = now()->year;
$month = now()->month;
$startDate = Carbon::create($year, $month, 1);
$endDate = Carbon::create($year, $month, 1)->endOfMonth();

$workingDays = [];
$currentDate = $startDate->copy();

while ($currentDate <= $endDate) {
    if ($currentDate->dayOfWeek >= 1 && $currentDate->dayOfWeek <= 5) {
        $workingDays[] = [
            'day' => $currentDate->day,
            'date' => $currentDate->format('Y-m-d'),
            'dayName' => $currentDate->format('D')
        ];
    }
    $currentDate->addDay();
}

echo "Total hari kerja: " . count($workingDays) . "\n";
foreach ($workingDays as $day) {
    echo "- {$day['day']} ({$day['dayName']}): {$day['date']}\n";
}

echo "\n";

// Cek apakah ada data untuk hari kerja
echo "🔍 Data Absensi untuk Hari Kerja:\n";
foreach ($workingDays as $day) {
    $count = Attendance::where('date', $day['date'])->count();
    echo "- {$day['date']} ({$day['dayName']}): {$count} records\n";
}

echo "\n✅ Pengecekan selesai!\n"; 