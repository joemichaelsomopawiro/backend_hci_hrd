<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('program_notifications')) {
            Schema::create('program_notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('program_id')->nullable()->constrained('pr_programs')->onDelete('cascade');
                $table->foreignId('episode_id')->nullable()->constrained('pr_episodes')->onDelete('cascade');
                $table->string('title');
                $table->text('message');
                $table->string('type')->default('program_notification'); // e.g., 'deadline_reminder', 'workflow_update'
                $table->boolean('is_read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->json('data')->nullable(); // Additional data
                $table->timestamps();

                $table->index('user_id');
                $table->index('is_read');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_notifications');
    }
};
