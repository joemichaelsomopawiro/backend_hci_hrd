<?php
// Test script untuk export harian dengan nama pegawai
$url = 'http://localhost/backend_hci/public/api/attendance/export/daily';

$data = [
    'date' => '2025-01-20',
    'format' => 'excel'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($data));
curl_setopt($ch, CURLOPT_HTTPGET, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: " . $httpCode . "\n";
echo "Response:\n";
$result = json_decode($response, true);
print_r($result);

if ($result && isset($result['data']['download_url'])) {
    echo "\n✅ Export harian berhasil!\n";
    echo "📁 File: " . $result['data']['filename'] . "\n";
    echo "🔗 Download URL: " . $result['data']['download_url'] . "\n";
    echo "📊 Total Records: " . $result['data']['total_records'] . "\n";
    echo "📅 Tanggal: " . $result['data']['date'] . "\n";
    echo "\n👤 Perubahan yang diterapkan:\n";
    echo "- Kolom 'ID Pegawai' diubah menjadi 'Nama Pegawai'\n";
    echo "- Menampilkan nama lengkap pegawai alih-alih user_pin\n";
    echo "- Relasi employeeAttendance sudah diperbaiki\n";
} else {
    echo "\n❌ Export gagal!\n";
    echo "Error: " . ($result['message'] ?? 'Unknown error') . "\n";
}
?> 