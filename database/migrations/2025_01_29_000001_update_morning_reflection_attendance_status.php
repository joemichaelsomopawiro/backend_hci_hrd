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
        // Update enum status untuk menambahkan 'Cuti'
        DB::statement("ALTER TABLE morning_reflection_attendance MODIFY COLUMN status ENUM('Hadir', 'Terlambat', 'Absen', 'Cuti') DEFAULT 'Hadir'");
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