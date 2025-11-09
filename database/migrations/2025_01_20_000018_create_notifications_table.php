<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Notifications table - Notification system
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('type'); // Type notification
            $table->string('title'); // Judul notification
            $table->text('message'); // Pesan notification
            $table->json('data')->nullable(); // Data tambahan
            $table->enum('status', [
                'unread',           // Belum dibaca
                'read',             // Sudah dibaca
                'archived'          // Diarsipkan
            ])->default('unread');
            
            // Related Information
            $table->foreignId('episode_id')->nullable()->constrained('episodes')->onDelete('cascade');
            $table->foreignId('program_id')->nullable()->constrained('programs')->onDelete('cascade');
            $table->string('related_type')->nullable(); // Type related object
            $table->unsignedBigInteger('related_id')->nullable(); // ID related object
            
            // Priority
            $table->enum('priority', [
                'low',              // Prioritas rendah
                'normal',           // Prioritas normal
                'high',             // Prioritas tinggi
                'urgent'            // Prioritas mendesak
            ])->default('normal');
            
            // Timing
            $table->timestamp('read_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('scheduled_at')->nullable(); // Untuk scheduled notifications
            
            $table->timestamps();
            
            // Indexes
            $table->index('user_id');
            $table->index('type');
            $table->index('status');
            $table->index('priority');
            $table->index('episode_id');
            $table->index('program_id');
            $table->index('scheduled_at');
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};














