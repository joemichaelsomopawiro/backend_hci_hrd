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
            // Hanya tambahkan index token jika belum ada
            // Index expires_at dan last_used_at sudah ditangani oleh migration sebelumnya
            if (! $this->indexExists('personal_access_tokens', 'pat_token_index')) {
                $table->index('token', 'pat_token_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            if ($this->indexExists('personal_access_tokens', 'pat_token_index')) {
                $table->dropIndex('pat_token_index');
            }
            // Do not drop columns here; handled by separate migration if needed
        });
    }

    /**
     * Helper: check index exists.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $database = Schema::getConnection()->getDatabaseName();
        $result = DB::select(
            "SELECT COUNT(1) as cnt
             FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ?",
            [$database, $table, $indexName]
        );
        return !empty($result) && $result[0]->cnt > 0;
    }
};
