<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if table exists
        if (!Schema::hasTable('morning_reflection_attendance')) {
            return;
        }
        
        // Check if column exists
        if (!Schema::hasColumn('morning_reflection_attendance', 'status')) {
            return;
        }
        
        try {
            // Get current enum values
            $result = DB::select("SHOW COLUMNS FROM morning_reflection_attendance WHERE Field = 'status'");
            if (!empty($result)) {
                $currentType = $result[0]->Type;
                // Check if 'Cuti' is already in the enum
                if (strpos($currentType, 'Cuti') === false) {
                    // Update enum status untuk menambahkan 'Cuti'
                    DB::statement("ALTER TABLE morning_reflection_attendance MODIFY COLUMN status ENUM('Hadir', 'Terlambat', 'Absen', 'Cuti') DEFAULT 'Hadir'");
                }
            }
        } catch (\Exception $e) {
            // Enum change failed, skip
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Kembalikan ke enum semula
        DB::statement("ALTER TABLE morning_reflection_attendance MODIFY COLUMN status ENUM('Hadir', 'Terlambat', 'Absen') DEFAULT 'Hadir'");
    }
};