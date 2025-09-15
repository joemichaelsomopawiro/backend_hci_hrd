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
        // Tahap aman: izinkan sementara nilai lama 'Cuti' agar alter tidak gagal
        DB::statement("ALTER TABLE morning_reflection_attendance MODIFY COLUMN status ENUM('Hadir','Terlambat','Absen','Cuti','izin') DEFAULT 'Hadir'");

        // Migrasi data: ubah semua 'Cuti' menjadi 'izin'
        DB::statement("UPDATE morning_reflection_attendance SET status = 'izin' WHERE status = 'Cuti'");

        // Rapikan enum final: hapus 'Cuti', pertahankan 'izin'
        DB::statement("ALTER TABLE morning_reflection_attendance MODIFY COLUMN status ENUM('Hadir','Terlambat','Absen','izin') DEFAULT 'Hadir'");
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