<?php

echo "=== FIX ZOOM ATTENDANCE PRODUCTION ISSUE ===\n";

echo "\n🔍 MASALAH YANG DITEMUKAN:\n";
echo "1. File .env memiliki error parsing dotenv\n";
echo "2. Environment production tapi APP_DEBUG=true\n";
echo "3. MAIL_HOST=mailpit mungkin menyebabkan whitespace error\n";

echo "\n🛠️  LANGKAH PERBAIKAN:\n";

echo "\n1. PERBAIKI FILE .ENV:\n";
echo "   Ganti bagian berikut di file .env:\n";
echo "\n   SEBELUM:\n";
echo "   APP_DEBUG=true\n";
echo "   MAIL_HOST=mailpit\n";
echo "   MAIL_PORT=1025\n";
echo "   MAIL_ENCRYPTION=null\n";
echo "\n   SESUDAH:\n";
echo "   APP_DEBUG=false\n";
echo "   MAIL_HOST=smtp.gmail.com\n";
echo "   MAIL_PORT=587\n";
echo "   MAIL_ENCRYPTION=tls\n";

echo "\n2. TAMBAHKAN QUOTES PADA PASSWORD DB:\n";
echo "   Ganti:\n";
echo "   DB_PASSWORD=|LlK/g6Ba\n";
echo "   Menjadi:\n";
echo '   DB_PASSWORD="|LlK/g6Ba"' . "\n";

echo "\n3. CLEAR CACHE SETELAH PERBAIKAN:\n";
echo "   php artisan config:clear\n";
echo "   php artisan cache:clear\n";
echo "   php artisan route:clear\n";

echo "\n📋 SCRIPT TEST ZOOM ATTENDANCE:\n";

// Buat script test sederhana
$testScript = '<?php

require_once "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make("Illuminate\Contracts\Console\Kernel")->bootstrap();

use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

// Test endpoint zoom attendance
$baseUrl = "https://api.hopemedia.id/api/ga";
$testData = [
    "employee_id" => 1,
    "zoom_link" => "https://zoom.us/j/test",
    "skip_time_validation" => true
];

echo "Testing Zoom Attendance...\n";
echo "URL: $baseUrl/zoom-join\n";
echo "Data: " . json_encode($testData) . "\n";

try {
    $response = Http::post($baseUrl . "/zoom-join", $testData);
    echo "Status: " . $response->status() . "\n";
    echo "Response: " . $response->body() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>';

file_put_contents('test_zoom_simple.php', $testScript);
echo "   ✅ File test_zoom_simple.php telah dibuat\n";

echo "\n4. JALANKAN TEST:\n";
echo "   php test_zoom_simple.php\n";

echo "\n🎯 KEMUNGKINAN PENYEBAB MASALAH DI PRODUCTION:\n";
echo "1. ⏰ WAKTU - Absensi zoom hanya bisa jam 07:10-08:00\n";
echo "2. 🔒 VALIDASI - Environment production lebih ketat\n";
echo "3. 🌐 HTTPS - Production menggunakan HTTPS, localhost HTTP\n";
echo "4. 📊 RATE LIMIT - Middleware rate limiting\n";
echo "5. 🗃️  DATABASE - Connection atau constraint issues\n";

echo "\n💡 SOLUSI UNTUK SETIAP MASALAH:\n";

echo "\n1. MASALAH WAKTU:\n";
echo "   - Pastikan akses dalam jam 07:10-08:00 WIB\n";
echo "   - Atau gunakan skip_time_validation=true untuk testing\n";

echo "\n2. MASALAH ENVIRONMENT:\n";
echo "   - Set APP_DEBUG=false di production\n";
echo "   - Pastikan APP_ENV=production\n";
echo "   - Clear cache setelah perubahan\n";

echo "\n3. MASALAH HTTPS:\n";
echo "   - Pastikan SSL certificate valid\n";
echo "   - Test dengan curl untuk memastikan endpoint accessible\n";

echo "\n4. MASALAH RATE LIMITING:\n";
echo "   - Check middleware AttendanceRateLimit\n";
echo "   - Reset rate limit jika perlu\n";

echo "\n5. MASALAH DATABASE:\n";
echo "   - Pastikan employee_id exists di tabel employees\n";
echo "   - Check unique constraint di tabel morning_reflection_attendance\n";

// Buat script untuk reset rate limit
$resetRateLimitScript = '<?php

require_once "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make("Illuminate\Contracts\Console\Kernel")->bootstrap();

use Illuminate\Support\Facades\Cache;

// Reset rate limit untuk IP tertentu
$ip = $_SERVER["HTTP_X_FORWARDED_FOR"] ?? $_SERVER["REMOTE_ADDR"] ?? "127.0.0.1";
$key = "attendance_rate_limit_" . $ip;

Cache::forget($key);
echo "Rate limit reset untuk IP: $ip\n";
echo "Key yang direset: $key\n";

?>';

file_put_contents('reset_rate_limit.php', $resetRateLimitScript);
echo "\n   ✅ File reset_rate_limit.php telah dibuat\n";

echo "\n📞 PANDUAN TESTING LANGKAH-DEMI-LANGKAH:\n";
echo "1. Perbaiki file .env sesuai panduan di atas\n";
echo "2. Jalankan: php artisan config:clear\n";
echo "3. Jalankan: php test_zoom_simple.php\n";
echo "4. Jika masih error, jalankan: php reset_rate_limit.php\n";
echo "5. Cek logs: tail -f storage/logs/laravel.log\n";

echo "\n🚀 ENDPOINT YANG TERSEDIA UNTUK ZOOM ATTENDANCE:\n";
echo "1. POST /api/ga/zoom-join (Production)\n";
echo "2. POST /api/morning-reflection/join (Alternative)\n";
echo "3. POST /api/morning-reflection/attend (With Auth)\n";

echo "\n📝 PARAMETER YANG DIPERLUKAN:\n";
echo "- employee_id (required, integer)\n";
echo "- zoom_link (optional, string)\n";
echo "- skip_time_validation (optional, boolean) - untuk testing\n";

echo "\n=== SELESAI ===\n";
echo "Silakan ikuti langkah-langkah di atas untuk memperbaiki masalah!\n";

?> 