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
            // Tambahkan last_used_at dulu
            if (!$this->columnExists('personal_access_tokens', 'last_used_at')) {
                $table->timestamp('last_used_at')->nullable()->after('updated_at');
            }
            // Baru tambahkan expires_at setelah last_used_at
            if (!$this->columnExists('personal_access_tokens', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('last_used_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            if ($this->columnExists('personal_access_tokens', 'expires_at')) {
                $table->dropColumn('expires_at');
            }
            if ($this->columnExists('personal_access_tokens', 'last_used_at')) {
                $table->dropColumn('last_used_at');
            }
        });
    }

    /**
     * Check if column exists using raw SQL
     */
    private function columnExists(string $table, string $column): bool
    {
        $database = Schema::getConnection()->getDatabaseName();
        $result = DB::select(
            "SELECT COUNT(*) as cnt 
             FROM information_schema.columns 
             WHERE table_schema = ? AND table_name = ? AND column_name = ?",
            [$database, $table, $column]
        );
        return !empty($result) && $result[0]->cnt > 0;
    }
};
