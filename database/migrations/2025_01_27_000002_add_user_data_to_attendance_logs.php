<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Check if table exists
        if (!Schema::hasTable('attendance_logs')) {
            return;
        }
        
        Schema::table('attendance_logs', function (Blueprint $table) {
            // Tambah kolom untuk data user dari mesin (only if they don't exist)
            if (!Schema::hasColumn('attendance_logs', 'user_name')) {
                $table->string('user_name', 100)->nullable()->comment('Nama user dari mesin absensi')->after('user_pin');
            }
            if (!Schema::hasColumn('attendance_logs', 'card_number')) {
                $table->string('card_number', 20)->nullable()->comment('Nomor kartu dari mesin absensi')->after('user_name');
            }
        });
    }

    public function down()
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropColumn(['user_name', 'card_number']);
        });
    }
}; 