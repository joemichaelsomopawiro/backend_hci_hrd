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
        Schema::table('pr_programs', function (Blueprint $table) {
            $table->boolean('read_by_producer')->default(false)->after('manager_distribusi_id');
            $table->timestamp('read_at')->nullable()->after('read_by_producer');

            // Add index for efficient filtering
            $table->index('read_by_producer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pr_programs', function (Blueprint $table) {
            $table->dropIndex(['read_by_producer']);
            $table->dropColumn(['read_by_producer', 'read_at']);
        });
    }
};
