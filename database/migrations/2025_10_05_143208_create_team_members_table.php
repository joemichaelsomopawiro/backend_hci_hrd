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
        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('role', ['member', 'lead', 'assistant'])->default('member');
            $table->boolean('is_active')->default(true);
            $table->date('joined_at')->default(now());
            $table->date('left_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unique(['team_id', 'user_id']); // Satu user hanya bisa dalam satu team per program
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_members');
    }
};
