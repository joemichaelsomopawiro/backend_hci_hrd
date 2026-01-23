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
        Schema::table('programs', function (Blueprint $table) {
            // Add proposal_file_link field for external proposal storage URLs
            // This replaces the need for proposal_file_path, proposal_file_name, etc
            // but we keep those fields for backward compatibility (nullable)
            $table->text('proposal_file_link')->nullable()->after('broadcast_channel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn('proposal_file_link');
        });
    }
};
