<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AttendanceLog;
use App\Models\Attendance;
use Illuminate\Support\Facades\DB;

echo "🧹 Clean and Fresh Sync from Machine X304...\n\n";

// Ask user for confirmation
echo "⚠️  PERINGATAN: Script ini akan:\n";
echo "1. Hapus SEMUA data di tabel attendance_logs\n";
echo "2. Hapus SEMUA data di tabel attendances\n";
echo "3. Fresh sync dari mesin X304\n\n";

echo "Apakah Anda yakin? (y/N): ";
$handle = fopen("php://stdin", "r");
$confirmation = trim(fgets($handle));
fclose($handle);

if (strtolower($confirmation) !== 'y') {
    echo "❌ Operasi dibatalkan.\n";
    exit(0);
}

echo "\n🗑️  Step 1: Membersihkan tabel attendance_logs...\n";
$logsCount = AttendanceLog::count();
AttendanceLog::truncate();
echo "✅ Berhasil hapus {$logsCount} records dari attendance_logs\n";

echo "\n🗑️  Step 2: Membersihkan tabel attendances...\n";
$attendanceCount = Attendance::count();
Attendance::truncate();
echo "✅ Berhasil hapus {$attendanceCount} records dari attendances\n";

echo "\n📡 Step 3: Fresh sync dari mesin X304...\n";
echo "Silakan jalankan:\n";
echo "curl -X POST http://localhost:8000/api/attendance/sync-today\n\n";

echo "✅ Pembersihan selesai! Tabel sudah bersih dan siap untuk fresh sync.\n";
echo "🎯 Sekarang data akan murni dari mesin X304 tanpa data percobaan sebelumnya.\n";
?> 