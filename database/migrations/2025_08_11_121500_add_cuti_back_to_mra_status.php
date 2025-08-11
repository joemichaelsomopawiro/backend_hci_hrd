<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Tambahkan kembali 'Cuti' sebagai nilai enum yang valid
        DB::statement("ALTER TABLE morning_reflection_attendance MODIFY COLUMN status ENUM('Hadir','Terlambat','Absen','izin','Cuti') NOT NULL DEFAULT 'Hadir'");
    }

    public function down(): void
    {
        // Kembali ke enum tanpa 'Cuti' (izin tetap ada)
        DB::statement("ALTER TABLE morning_reflection_attendance MODIFY COLUMN status ENUM('Hadir','Terlambat','Absen','izin') NOT NULL DEFAULT 'Hadir'");
    }
};



