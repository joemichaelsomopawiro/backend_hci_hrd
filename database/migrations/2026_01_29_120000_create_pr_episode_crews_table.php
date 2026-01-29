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
        Schema::create('pr_episode_crews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('episode_id'); // maps to pr_episodes.id
            $table->unsignedBigInteger('user_id'); // maps to users.id
            $table->string('role'); // 'shooting_team' or 'setting_team'
            $table->timestamps();

            $table->foreign('episode_id')->references('id')->on('pr_episodes')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pr_episode_crews');
    }
};
