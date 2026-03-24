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
        Schema::create('pr_program_team_presets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('manager_program_id');
            $table->string('name');
            $table->json('data');
            $table->timestamps();

            $table->foreign('manager_program_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pr_program_team_presets');
    }
};
