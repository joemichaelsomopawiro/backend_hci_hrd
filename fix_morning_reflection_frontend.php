<?php
// Fix Morning Reflection Frontend Configuration
echo "=== FIX MORNING REFLECTION FRONTEND ===\n";

echo "\n🎯 DIAGNOSIS MASALAH:\n";
echo "=====================\n";
echo "✅ Server API berfungsi normal (api.hopemedia.id)\n";
echo "✅ Endpoint /morning-reflection/attendance ada dan berfungsi\n";
echo "❌ Frontend masih menggunakan domain lama atau konfigurasi salah\n";
echo "❌ Authentication token mungkin expired\n\n";

echo "🔧 LANGKAH PERBAIKAN:\n";
echo "====================\n";

// 1. Clear Laravel Cache
echo "\n1. Clear Laravel Cache:\n";
echo "-----------------------\n";

$commands = [
    'config:clear' => 'Clear configuration cache',
    'cache:clear' => 'Clear application cache', 
    'route:clear' => 'Clear route cache',
    'view:clear' => 'Clear view cache'
];

foreach ($commands as $command => $description) {
    echo "Running: php artisan $command ($description)\n";
    
    $output = shell_exec("php artisan $command 2>&1");
    if ($output) {
        echo "Output: " . trim($output) . "\n";
    } else {
        echo "✅ Success\n";
    }
}

// 2. Check current configuration
echo "\n\n2. Current Configuration:\n";
echo "========================\n";

$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);
    
    if (preg_match('/APP_URL=(.*)/', $envContent, $matches)) {
        $appUrl = trim($matches[1]);
        echo "APP_URL: $appUrl\n";
        
        if (strpos($appUrl, 'api.hopemedia.id') !== false) {
            echo "✅ Backend sudah menggunakan domain baru\n";
        } else {
            echo "⚠️  Backend belum menggunakan domain baru\n";
        }
    }
}

// 3. Frontend Configuration
echo "\n\n3. Frontend Configuration Update:\n";
echo "=================================\n";
echo "Update konfigurasi axios di frontend dengan:\n\n";

echo "// === UPDATE INI DI FRONTEND ===\n";
echo "const api = axios.create({\n";
echo "    baseURL: 'https://api.hopemedia.id/api',\n";
echo "    timeout: 30000,\n";
echo "    headers: {\n";
echo "        'Content-Type': 'application/json',\n";
echo "        'Accept': 'application/json'\n";
echo "    }\n";
echo "});\n\n";

echo "// Atau jika menggunakan environment variables:\n";
echo "// VITE_API_URL=https://api.hopemedia.id/api\n";
echo "// REACT_APP_API_URL=https://api.hopemedia.id/api\n";
echo "// NUXT_PUBLIC_API_URL=https://api.hopemedia.id/api\n\n";

// 4. Test Authentication
echo "\n4. Test Authentication:\n";
echo "======================\n";
echo "Untuk test apakah authentication berfungsi:\n\n";

echo "A. Login melalui frontend dan copy token dari browser:\n";
echo "   - Buka Developer Tools (F12)\n";
echo "   - Tab Network\n";
echo "   - Login di frontend\n";
echo "   - Cari request ke /auth/login\n";
echo "   - Copy token dari response\n\n";

echo "B. Test dengan token:\n";
$testUrl = 'https://api.hopemedia.id/api/morning-reflection/attendance?employee_id=13&per_page=20&page=1&sort=date_desc';
echo "curl -H \"Authorization: Bearer YOUR_TOKEN\" \\\n";
echo "     -H \"Content-Type: application/json\" \\\n";
echo "     -H \"Accept: application/json\" \\\n";
echo "     \"$testUrl\"\n\n";

// 5. Browser fixes
echo "\n5. Browser Fixes:\n";
echo "=================\n";
echo "Untuk mengatasi ERR_QUIC_PROTOCOL_ERROR:\n\n";

echo "A. Clear Browser Cache:\n";
echo "   - Tekan Ctrl+Shift+Delete\n";
echo "   - Pilih 'All time'\n";
echo "   - Clear cache dan cookies\n\n";

echo "B. Disable QUIC Protocol (jika masih error):\n";
echo "   - Chrome: chrome://flags/#enable-quic\n";
echo "   - Set ke 'Disabled'\n";
echo "   - Restart browser\n\n";

echo "C. Test dengan Incognito Mode:\n";
echo "   - Buka incognito/private window\n";
echo "   - Test login dan morning reflection\n\n";

// 6. Rate limiting reset
echo "\n6. Reset Rate Limiting (jika diperlukan):\n";
echo "========================================\n";
echo "Jika ada rate limiting, jalankan:\n";
echo "curl -X POST https://api.hopemedia.id/api/morning-reflection/reset-rate-limit\n\n";

// 7. Debug endpoint
echo "\n7. Debug Endpoints Available:\n";
echo "============================\n";
echo "Untuk debugging lebih lanjut:\n";
echo "- https://api.hopemedia.id/api/morning-reflection/test-db\n";
echo "- https://api.hopemedia.id/api/morning-reflection/status\n\n";

echo "=== RINGKASAN SOLUSI ===\n";
echo "\n🎯 YANG HARUS DILAKUKAN:\n";
echo "\n1. ✅ Update frontend baseURL ke: https://api.hopemedia.id/api\n";
echo "2. ✅ Clear browser cache dan cookies\n";
echo "3. ✅ Login ulang untuk refresh authentication token\n";
echo "4. ✅ Test dengan incognito mode jika masih error\n";
echo "5. ✅ Disable QUIC protocol di browser jika diperlukan\n\n";

echo "💡 ERROR ERR_QUIC_PROTOCOL_ERROR biasanya disebabkan oleh:\n";
echo "   - Browser cache yang corrupt\n";
echo "   - Authentication token expired\n";
echo "   - QUIC protocol compatibility issue\n\n";

echo "🔄 Setelah update frontend, test kembali morning reflection!\n"; 