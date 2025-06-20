<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Pertama, update semua data yang tidak sesuai dengan enum menjadi 'Employee'
        DB::table('employees')
            ->whereNotIn('jabatan_saat_ini', ['HR', 'Manager', 'Employee', 'GA'])
            ->update(['jabatan_saat_ini' => 'Employee']);
        
        // Kemudian ubah kolom menjadi enum
        Schema::table('employees', function (Blueprint $table) {
            $table->enum('jabatan_saat_ini', ['HR', 'Manager', 'Employee', 'GA'])->default('Employee')->change();
        });
    }

    public function down()
    {
        Schema::table('employees', function (Blueprint $table) {
            // Kembalikan ke string
            $table->string('jabatan_saat_ini', 100)->change();
        });
    }
};