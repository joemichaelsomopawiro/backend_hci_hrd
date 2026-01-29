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
        Schema::create('pr_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->nullable()->constrained('pr_programs')->onDelete('cascade');
            $table->foreignId('episode_id')->nullable()->constrained('pr_episodes')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('action');
            $table->text('description');
            $table->json('changes')->nullable();
            $table->timestamps();

            $table->index(['program_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pr_activity_logs');
    }
};
