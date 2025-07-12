<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Verifikasi Migration Morning Reflection Attendance ===\n\n";

try {
    // Cek apakah tabel ada
    if (Schema::hasTable('morning_reflection_attendance')) {
        echo "✓ Tabel morning_reflection_attendance ditemukan\n";
        
        // Cek struktur kolom status
        $columns = DB::select("SHOW COLUMNS FROM morning_reflection_attendance LIKE 'status'");
        
        if (!empty($columns)) {
            $statusColumn = $columns[0];
            echo "✓ Kolom status ditemukan\n";
            echo "  - Type: {$statusColumn->Type}\n";
            echo "  - Null: {$statusColumn->Null}\n";
            echo "  - Default: {$statusColumn->Default}\n";
            
            // Parse enum values
            if (preg_match("/enum\((.*)\)/", $statusColumn->Type, $matches)) {
                $enumValues = str_getcsv($matches[1], ',', "'");
                echo "  - Enum values: " . implode(', ', $enumValues) . "\n";
                
                // Cek apakah 'Cuti' ada dalam enum
                if (in_array('Cuti', $enumValues)) {
                    echo "✓ Status 'Cuti' sudah tersedia dalam enum\n";
                } else {
                    echo "✗ Status 'Cuti' belum tersedia dalam enum\n";
                }
            }
        } else {
            echo "✗ Kolom status tidak ditemukan\n";
        }
        
        // Test insert dengan status 'Cuti'
        echo "\n=== Test Insert dengan Status 'Cuti' ===\n";
        
        try {
            // Cek apakah ada employee yang valid untuk testing
            $validEmployee = DB::table('employees')->first();
            
            if ($validEmployee) {
                $testData = [
                    'employee_id' => $validEmployee->id,
                    'date' => '2025-01-15',
                    'status' => 'Cuti',
                    'join_time' => null,
                    'testing_mode' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                
                DB::table('morning_reflection_attendance')->insert($testData);
                echo "✓ Insert dengan status 'Cuti' berhasil\n";
                
                // Hapus data test
                DB::table('morning_reflection_attendance')
                    ->where('employee_id', $validEmployee->id)
                    ->where('date', '2025-01-15')
                    ->delete();
                echo "✓ Data test berhasil dihapus\n";
            } else {
                echo "⚠ Tidak ada employee untuk testing\n";
            }
            
        } catch (Exception $e) {
            echo "✗ Error saat test insert: " . $e->getMessage() . "\n";
        }
        
    } else {
        echo "✗ Tabel morning_reflection_attendance tidak ditemukan\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Selesai ===\n"; 