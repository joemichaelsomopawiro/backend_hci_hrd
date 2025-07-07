<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Tambah kolom untuk data langsung dari mesin
            $table->string('user_pin', 20)->nullable()->comment('PIN/UserID dari mesin absensi')->after('employee_id');
            $table->string('user_name', 100)->nullable()->comment('Nama user dari mesin absensi')->after('user_pin');
            $table->string('card_number', 20)->nullable()->comment('Nomor kartu dari mesin absensi')->after('user_name');
            
            // Buat employee_id menjadi nullable
            $table->foreignId('employee_id')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['user_pin', 'user_name', 'card_number']);
            $table->foreignId('employee_id')->nullable(false)->change();
        });
    }
}; 