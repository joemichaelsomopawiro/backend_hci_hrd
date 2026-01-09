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
            $table->string('proposal_file_path')->nullable()->after('description');
            $table->string('proposal_file_name')->nullable()->after('proposal_file_path');
            $table->bigInteger('proposal_file_size')->nullable()->after('proposal_file_name');
            $table->string('proposal_file_mime_type')->nullable()->after('proposal_file_size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn([
                'proposal_file_path',
                'proposal_file_name',
                'proposal_file_size',
                'proposal_file_mime_type'
            ]);
        });
    }
};
