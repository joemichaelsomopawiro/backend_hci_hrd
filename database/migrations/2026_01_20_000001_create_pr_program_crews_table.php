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
        Schema::create('pr_program_crews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('pr_programs')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('role'); // Producer, Kreatif, Musik Arr, etc.
            $table->timestamps();

            // Prevent duplicate user assignment in same program
            $table->unique(['program_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pr_program_crews');
    }
};
