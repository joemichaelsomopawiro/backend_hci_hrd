<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if table already exists
        if (!Schema::hasTable('employee_attendance')) {
            Schema::create('employee_attendance', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attendance_machine_id');
            
            // Data dari mesin Solution X304
            $table->string('machine_user_id')->comment('ID Number dari mesin');
            $table->string('name')->comment('Nama dari mesin');
            $table->string('card_number')->nullable()->comment('Card number dari mesin');
            $table->string('department')->nullable()->comment('Department dari mesin');
            $table->string('privilege')->nullable()->comment('Privilege dari mesin (User, Admin, etc)');
            $table->string('group_name')->nullable()->comment('Group dari mesin');
            
            // Status dan metadata
            $table->boolean('is_active')->default(true);
            $table->json('raw_data')->nullable()->comment('Data mentah dari mesin');
            $table->timestamp('last_seen_at')->nullable()->comment('Terakhir terlihat di mesin');
            
            $table->timestamps();
            
            // Indexes
            $table->index(['attendance_machine_id', 'machine_user_id']);
            $table->index('machine_user_id');
            $table->index('card_number');
            $table->index('name');
            
            // Foreign key
            $table->foreign('attendance_machine_id')->references('id')->on('attendance_machines')->onDelete('cascade');
            
            // Unique constraint
            $table->unique(['attendance_machine_id', 'machine_user_id'], 'unique_machine_user');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_attendance');
    }
};
