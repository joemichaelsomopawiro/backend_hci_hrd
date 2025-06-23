<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Update existing data yang tidak sesuai dengan enum baru
        DB::table('employees')
            ->whereNotIn('jabatan_saat_ini', [
                'HR', 'Program Manager', 'Distribution Manager', 'GA',
                'Finance', 'General Affairs', 'Office Assistant',
                'Producer', 'Creative', 'Production', 'Editor',
                'Social Media', 'Promotion', 'Graphic Design', 'Hopeline Care',
                'Employee'
            ])
            ->update(['jabatan_saat_ini' => 'Employee']);
        
        // Update enum column untuk match dengan role di tabel users
        Schema::table('employees', function (Blueprint $table) {
            $table->enum('jabatan_saat_ini', [
                'HR', 
                'Program Manager', 
                'Distribution Manager', 
                'GA',
                'Finance', 
                'General Affairs', 
                'Office Assistant',
                'Producer', 
                'Creative', 
                'Production', 
                'Editor',
                'Social Media', 
                'Promotion', 
                'Graphic Design', 
                'Hopeline Care',
                'Employee'
            ])->default('Employee')->change();
        });
    }

    public function down()
    {
        // Kembalikan ke enum sebelumnya
        Schema::table('employees', function (Blueprint $table) {
            $table->enum('jabatan_saat_ini', ['HR', 'Manager', 'Employee', 'GA'])->default('Employee')->change();
        });
    }
};