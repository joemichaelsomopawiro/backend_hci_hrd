<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            // Tambah kolom untuk data user dari mesin
            $table->string('user_name', 100)->nullable()->comment('Nama user dari mesin absensi')->after('user_pin');
            $table->string('card_number', 20)->nullable()->comment('Nomor kartu dari mesin absensi')->after('user_name');
        });
    }

    public function down()
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropColumn(['user_name', 'card_number']);
        });
    }
}; 