<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Cek apakah tabel sudah ada sebelum membuatnya
        if (!Schema::hasTable('attendance_machine_users')) {
            Schema::create('attendance_machine_users', function (Blueprint $table) {
                $table->id();
                $table->foreignId('attendance_machine_id')->constrained()->onDelete('cascade');
                $table->foreignId('employee_id')->constrained()->onDelete('cascade');
                $table->string('badge_number'); // NIP from employee
                $table->string('name');
                $table->enum('status', ['synced', 'pending', 'failed'])->default('pending');
                $table->timestamp('last_synced_at')->nullable();
                $table->text('sync_error')->nullable();
                $table->json('machine_user_data')->nullable(); // Additional user data from machine
                $table->timestamps();
                
                // Use custom shorter names for unique constraints
                $table->unique(['attendance_machine_id', 'employee_id'], 'amu_machine_employee_unique');
                $table->unique(['attendance_machine_id', 'badge_number'], 'amu_machine_badge_unique');
                $table->index(['status', 'last_synced_at']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('attendance_machine_users');
    }
};