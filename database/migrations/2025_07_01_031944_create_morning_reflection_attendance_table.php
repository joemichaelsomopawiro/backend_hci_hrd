<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('morning_reflection_attendance')) {
            Schema::create('morning_reflection_attendance', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained()->onDelete('cascade');
                $table->date('date');
                $table->enum('status', ['Hadir', 'Terlambat', 'Absen'])->default('Hadir');
                $table->timestamp('join_time')->nullable();
                $table->boolean('testing_mode')->default(false);
                $table->timestamps();
                
                // Unique constraint untuk mencegah duplikasi
                $table->unique(['employee_id', 'date'], 'unique_employee_date_attendance');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('morning_reflection_attendance');
    }
};