<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1) Izinkan sementara nilai lama 'Cuti' dan nilai baru 'izin'
        DB::statement("ALTER TABLE morning_reflection_attendance MODIFY COLUMN status ENUM('Hadir','Terlambat','Absen','Cuti','izin') DEFAULT 'Hadir'");

        // 2) Migrasi data lama: ubah semua 'Cuti' menjadi 'izin'
        DB::statement("UPDATE morning_reflection_attendance SET status = 'izin' WHERE status = 'Cuti'");

        // 3) Rapikan enum final: hapus 'Cuti', pertahankan 'izin'
        DB::statement("ALTER TABLE morning_reflection_attendance MODIFY COLUMN status ENUM('Hadir','Terlambat','Absen','izin') DEFAULT 'Hadir'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1) Izinkan kembali 'Cuti' bersamaan dengan 'izin'
        DB::statement("ALTER TABLE morning_reflection_attendance MODIFY COLUMN status ENUM('Hadir','Terlambat','Absen','Cuti','izin') DEFAULT 'Hadir'");

        // 2) Kembalikan data 'izin' menjadi 'Cuti' (best-effort)
        DB::statement("UPDATE morning_reflection_attendance SET status = 'Cuti' WHERE status = 'izin'");

        // 3) Kembalikan enum lama tanpa 'izin'
        DB::statement("ALTER TABLE morning_reflection_attendance MODIFY COLUMN status ENUM('Hadir','Terlambat','Absen','Cuti') DEFAULT 'Hadir'");
    }
};



