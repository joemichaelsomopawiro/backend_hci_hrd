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
        Schema::create('program_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            $table->enum('type', ['deadline', 'reminder', 'assignment', 'status_change', 'general'])->default('general');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('program_id')->nullable()->constrained('programs')->onDelete('cascade');
            $table->foreignId('episode_id')->nullable()->constrained('episodes')->onDelete('cascade');
            $table->foreignId('schedule_id')->nullable()->constrained('schedules')->onDelete('cascade');
            $table->boolean('is_read')->default(false);
            $table->datetime('read_at')->nullable();
            $table->datetime('scheduled_at')->nullable(); // Untuk notifikasi terjadwal
            $table->json('data')->nullable(); // Data tambahan untuk notifikasi
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_notifications');
    }
};
