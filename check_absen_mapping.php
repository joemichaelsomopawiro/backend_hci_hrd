<?php
// Script untuk cek mapping data present_ontime di database dan hasil API monthly-table
require_once 'vendor/autoload.php';

// Load Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Attendance;
use Illuminate\Support\Facades\Http;

$nama = 'John Doe'; // Ganti dengan nama yang pasti ada present_ontime di Juli 2025
$month = 7;
$year = 2025;

// 1. Cek di database
$records = Attendance::where('user_name', $nama)
    ->whereYear('date', $year)
    ->whereMonth('date', $month)
    ->where('status', 'present_ontime')
    ->orderBy('date')
    ->get();

echo "=== DATA present_ontime DI DATABASE UNTUK $nama BULAN $month-$year ===\n";
foreach ($records as $rec) {
    echo "Tanggal: {$rec->date}, Check In: {$rec->check_in}, Check Out: {$rec->check_out}\n";
}
if (count($records) === 0) {
    echo "Tidak ada data present_ontime untuk $nama di bulan ini.\n";
}

echo "\n";

// 2. Cek hasil API
$apiUrl = "https://api.hopemedia.id/api/attendance/monthly-table?month=$month&year=$year";
echo "=== CEK RESPONSE API: $apiUrl ===\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Test-Script/1.0');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
if (!$data || !$data['success']) {
    echo "Gagal ambil data dari API.\n";
    exit(1);
}

$found = false;
foreach ($data['data']['records'] as $row) {
    if (strtolower(trim($row['nama'])) === strtolower(trim($nama))) {
        echo "Data untuk $nama ditemukan di API.\n";
        foreach ($row['daily_data'] as $day => $info) {
            if ($info['status'] === 'present_ontime') {
                echo "Hari ke-$day: HADIR (Check In: {$info['check_in']}, Check Out: {$info['check_out']})\n";
                $found = true;
            }
        }
        break;
    }
}
if (!$found) {
    echo "Tidak ada status present_ontime untuk $nama di response API bulan ini.\n";
} 