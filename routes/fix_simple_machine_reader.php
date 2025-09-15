<?php
/**
 * Fix Simple Machine Reader
 * 
 * Script untuk memperbaiki machine reader agar bisa membaca file mesin yang simpel
 */

echo "=== FIX SIMPLE MACHINE READER ===\n\n";

// Baca file yang akan dimodifikasi
$filePath = 'app/Services/AttendanceExcelUploadService.php';
$content = file_get_contents($filePath);

if (!$content) {
    echo "❌ File tidak ditemukan: $filePath\n";
    exit(1);
}

echo "✅ File berhasil dibaca: $filePath\n";

// Ganti machine reader dengan versi yang lebih robust
$newMachineReaderCode = '
                    \'machine_raw\' => function($filePath) {
                        // Reader khusus untuk file raw dari mesin absensi (format simpel)
                        $content = file_get_contents($filePath);
                        
                        // Debug: Log content untuk analisis
                        \Illuminate\Support\Facades\Log::info("Machine file content preview", [
                            "first_100_chars" => substr($content, 0, 100),
                            "file_size" => strlen($content)
                        ]);
                        
                        // Coba berbagai encoding
                        $encodings = ["UTF-8", "ISO-8859-1", "Windows-1252"];
                        $lines = null;
                        
                        foreach ($encodings as $encoding) {
                            $decoded = mb_convert_encoding($content, "UTF-8", $encoding);
                            $lines = explode("\n", $decoded);
                            
                            // Cek apakah ada baris yang mengandung header yang diharapkan
                            $hasHeader = false;
                            foreach ($lines as $line) {
                                if (strpos($line, "No. ID") !== false || strpos($line, "Nama") !== false) {
                                    $hasHeader = true;
                                    break;
                                }
                            }
                            
                            if ($hasHeader) {
                                \Illuminate\Support\Facades\Log::info("Found valid header with encoding: $encoding");
                                break;
                            }
                        }
                        
                        if (!$lines) {
                            throw new \Exception("Tidak dapat decode file");
                        }
                        
                        $data = [];
                        $delimiters = ["\t", ";", ",", "|", ":"];
                        
                        foreach ($lines as $lineNum => $line) {
                            $line = trim($line);
                            if (empty($line)) continue;
                            
                            // Coba berbagai delimiter
                            $row = null;
                            $usedDelimiter = null;
                            
                            foreach ($delimiters as $delimiter) {
                                $parts = explode($delimiter, $line);
                                if (count($parts) >= 7) { // Minimal 7 kolom sesuai header
                                    $row = $parts;
                                    $usedDelimiter = $delimiter;
                                    break;
                                }
                            }
                            
                            if ($row) {
                                // Clean up data
                                $cleanRow = [];
                                foreach ($row as $cell) {
                                    $cleanRow[] = trim($cell, " \t\n\r\0\x0B\"");
                                }
                                $data[] = $cleanRow;
                                
                                // Log untuk debug
                                if ($lineNum < 3) { // Log 3 baris pertama
                                    \Illuminate\Support\Facades\Log::info("Parsed row $lineNum", [
                                        "delimiter" => $usedDelimiter,
                                        "parts" => count($cleanRow),
                                        "data" => $cleanRow
                                    ]);
                                }
                            }
                        }
                        
                        if (empty($data)) {
                            throw new \Exception("Tidak dapat parse data dari file");
                        }
                        
                        // Buat spreadsheet dari data yang berhasil di-parse
                        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                        $sheet = $spreadsheet->getActiveSheet();
                        
                        foreach ($data as $rowIndex => $row) {
                            foreach ($row as $colIndex => $value) {
                                $sheet->setCellValueByColumnAndRow($colIndex + 1, $rowIndex + 1, $value);
                            }
                        }
                        
                        \Illuminate\Support\Facades\Log::info("Machine file parsed successfully", [
                            "total_rows" => count($data),
                            "total_cols" => count($data[0] ?? [])
                        ]);
                        
                        return $spreadsheet;
                    },';

// Cari dan ganti machine reader yang lama
$oldMachineReaderPattern = '/\'machine_raw\' => function\(\$filePath\) \{[\s\S]*?\},/';
if (preg_match($oldMachineReaderPattern, $content)) {
    $content = preg_replace($oldMachineReaderPattern, $newMachineReaderCode, $content);
    echo "✅ Machine reader berhasil diperbaiki\n";
} else {
    echo "❌ Tidak dapat menemukan machine reader yang lama\n";
    exit(1);
}

// Backup file lama
$backupPath = $filePath . '.backup.simple.' . date('Y-m-d_H-i-s');
file_put_contents($backupPath, file_get_contents($filePath));
echo "✅ Backup file dibuat: $backupPath\n";

// Tulis file yang sudah dimodifikasi
file_put_contents($filePath, $content);
echo "✅ File berhasil dimodifikasi: $filePath\n";

// Verifikasi perubahan
echo "\n=== VERIFICATION ===\n";
$newContent = file_get_contents($filePath);
$checks = [
    'machine_raw' => 'Machine reader',
    'mb_convert_encoding' => 'Encoding support',
    'No. ID' => 'Header detection',
    'Log::info' => 'Debug logging'
];

foreach ($checks as $pattern => $description) {
    $count = substr_count($newContent, $pattern);
    echo "📊 $description: $count occurrence(s)\n";
}

echo "\n=== FIX COMPLETE ===\n";
echo "Sekarang jalankan:\n";
echo "1. php artisan cache:clear\n";
echo "2. Test upload file 30 Juni - 11 Juli.xls\n";
echo "3. Cek log Laravel untuk debug info\n";
echo "4. File sekarang akan dibaca dengan format simpel (tanpa warna hijau)\n";
?> 