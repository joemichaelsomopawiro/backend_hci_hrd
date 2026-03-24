<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('production_teams')) {
            return;
        }

        Schema::table('production_teams', function (Blueprint $table) {
            // Default index name for `$table->string('name')->unique()` is `production_teams_name_unique`
            // Drop it so we can allow reusing names after soft delete.
            try {
                $table->dropUnique('production_teams_name_unique');
            } catch (\Throwable $e) {
                // Index may have a different name in some environments; ignore and continue.
            }

            // Allow same name if the old row is soft-deleted (deleted_at not null).
            // MySQL allows multiple NULLs, so active rows (deleted_at NULL) remain unique by name.
            $table->unique(['name', 'deleted_at'], 'production_teams_name_deleted_at_unique');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('production_teams')) {
            return;
        }

        Schema::table('production_teams', function (Blueprint $table) {
            try {
                $table->dropUnique('production_teams_name_deleted_at_unique');
            } catch (\Throwable $e) {
                // ignore
            }

            $table->unique('name', 'production_teams_name_unique');
        });
    }
};

