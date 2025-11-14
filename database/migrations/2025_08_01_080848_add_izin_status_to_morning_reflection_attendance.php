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
        
        // Check if status column exists
        if (!Schema::hasColumn('morning_reflection_attendance', 'status')) {
            return;
        }
        
        try {
            // Check current enum values
            $result = DB::select("SHOW COLUMNS FROM morning_reflection_attendance WHERE Field = 'status'");
            if (!empty($result)) {
                $currentType = $result[0]->Type;
                // Check if 'izin' is already in the enum
                if (strpos($currentType, 'izin') === false) {
                    // Tahap aman: izinkan sementara nilai lama 'Cuti' agar alter tidak gagal
                    DB::statement("ALTER TABLE morning_reflection_attendance MODIFY COLUMN status ENUM('Hadir','Terlambat','Absen','Cuti','izin') DEFAULT 'Hadir'");

                    // Migrasi data: ubah semua 'Cuti' menjadi 'izin'
                    DB::statement("UPDATE morning_reflection_attendance SET status = 'izin' WHERE status = 'Cuti'");

                    // Rapikan enum final: hapus 'Cuti', pertahankan 'izin'
                    DB::statement("ALTER TABLE morning_reflection_attendance MODIFY COLUMN status ENUM('Hadir','Terlambat','Absen','izin') DEFAULT 'Hadir'");
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
        // Izinkan kembali 'Cuti' agar rollback aman
        DB::statement("ALTER TABLE morning_reflection_attendance MODIFY COLUMN status ENUM('Hadir','Terlambat','Absen','Cuti','izin') DEFAULT 'Hadir'");

        // Kembalikan data 'izin' menjadi 'Cuti'
        DB::statement("UPDATE morning_reflection_attendance SET status = 'Cuti' WHERE status = 'izin'");

        // Kembalikan enum tanpa 'izin'
        DB::statement("ALTER TABLE morning_reflection_attendance MODIFY COLUMN status ENUM('Hadir','Terlambat','Absen','Cuti') DEFAULT 'Hadir'");
    }
}; 