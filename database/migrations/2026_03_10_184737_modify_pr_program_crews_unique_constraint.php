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
        Schema::table('pr_program_crews', function (Blueprint $table) {
            // Drop Foreign Keys first (MySQL errno 150 prevention)
            $table->dropForeign(['program_id']);
            $table->dropForeign(['user_id']);

            // Drop the old unique constraint (program_id + user_id)
            $table->dropUnique(['program_id', 'user_id']);

            // Re-add Foreign Keys
            $table->foreign('program_id')->references('id')->on('pr_programs')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Add new unique constraint (program_id + user_id + role)
            // One person can hold multiple roles, but not same role twice
            $table->unique(['program_id', 'user_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pr_program_crews', function (Blueprint $table) {
            $table->dropUnique(['program_id', 'user_id', 'role']);
            $table->unique(['program_id', 'user_id']);
        });
    }
};
