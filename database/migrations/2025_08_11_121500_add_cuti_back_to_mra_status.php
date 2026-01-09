<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Check if table exists
        if (!Schema::hasTable('morning_reflection_attendance')) {
            return;
        }
        
        // Check if status column exists
        if (!Schema::hasColumn('morning_reflection_attendance', 'status')) {
            return;
        }
        
        try {
            // Check current enum values
            $result = DB::select("SHOW COLUMNS FROM morning_reflection_attendance WHERE Field = 'status'");
            if (!empty($result)) {
                $currentType = $result[0]->Type;
                // Check if 'Cuti' is not in the enum
                if (strpos($currentType, 'Cuti') === false) {
                    // Tambahkan kembali 'Cuti' sebagai nilai enum yang valid
                    DB::statement("ALTER TABLE morning_reflection_attendance MODIFY COLUMN status ENUM('Hadir','Terlambat','Absen','izin','Cuti') NOT NULL DEFAULT 'Hadir'");
                }
            }
        } catch (\Exception $e) {
            // Enum change failed, skip
        }
    }

    public function down(): void
    {
        // Kembali ke enum tanpa 'Cuti' (izin tetap ada)
        DB::statement("ALTER TABLE morning_reflection_attendance MODIFY COLUMN status ENUM('Hadir','Terlambat','Absen','izin') NOT NULL DEFAULT 'Hadir'");
    }
};



