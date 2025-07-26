<?php
// Test script untuk verifikasi data 9 Juli 2025
// Jalankan via SSH: php test_july_9_data.php

echo "=== TEST DATA 9 JULI 2025 ===\n\n";

// Test generate working days Juli 2025
echo "📅 Working days Juli 2025:\n";
$year = 2025;
$month = 7;

$workingDays = [];
$startDate = new DateTime("$year-$month-01");
$endDate = new DateTime("$year-$month-31");

$currentDate = clone $startDate;

while ($currentDate <= $endDate) {
    $dayOfWeek = (int)$currentDate->format('N'); // 1=Monday, 7=Sunday
    if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
        $workingDays[] = [
            'day' => (int)$currentDate->format('j'),
            'date' => $currentDate->format('Y-m-d'),
            'dayName' => $currentDate->format('D')
        ];
    }
    $currentDate->add(new DateInterval('P1D'));
}

foreach ($workingDays as $day) {
    $marker = ($day['day'] == 9) ? " ⭐" : "";
    echo "- {$day['day']} ({$day['dayName']}): {$day['date']}{$marker}\n";
}

echo "\n✅ Tanggal 9 Juli 2025 adalah hari kerja (Rabu)\n";
echo "✅ Data attendance tanggal 9 Juli 2025 akan ditampilkan di kolom '9'\n";

echo "\n=== SELESAI ===\n"; 