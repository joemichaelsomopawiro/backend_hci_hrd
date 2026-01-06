<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            // Indexes for cleanup queries - hanya tambahkan jika belum ada
            if (!$this->indexExists('personal_access_tokens', 'pat_expires_at_index')) {
                $table->index('expires_at', 'pat_expires_at_index');
            }
            if (!$this->indexExists('personal_access_tokens', 'pat_last_used_at_index')) {
                $table->index('last_used_at', 'pat_last_used_at_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            if ($this->indexExists('personal_access_tokens', 'pat_expires_at_index')) {
                $table->dropIndex('pat_expires_at_index');
            }
            if ($this->indexExists('personal_access_tokens', 'pat_last_used_at_index')) {
                $table->dropIndex('pat_last_used_at_index');
            }
        });
    }

    /**
     * Check if index exists using raw SQL
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $database = Schema::getConnection()->getDatabaseName();
        $result = DB::select(
            "SELECT COUNT(*) as cnt 
             FROM information_schema.statistics 
             WHERE table_schema = ? AND table_name = ? AND index_name = ?",
            [$database, $table, $indexName]
        );
        return !empty($result) && $result[0]->cnt > 0;
    }
};
