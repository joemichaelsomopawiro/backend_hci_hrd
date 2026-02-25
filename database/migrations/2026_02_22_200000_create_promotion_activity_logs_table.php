<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('promotion_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_work_id')->constrained('promotion_works')->onDelete('cascade');
            $table->foreignId('episode_id')->nullable()->constrained('episodes')->onDelete('set null');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('action'); // e.g. work_accepted, bts_video_uploaded, work_completed
            $table->text('description');
            $table->json('changes')->nullable(); // old/new status, file counts, metadata
            $table->timestamps();

            $table->index(['promotion_work_id', 'created_at']);
            $table->index(['episode_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_activity_logs');
    }
};
