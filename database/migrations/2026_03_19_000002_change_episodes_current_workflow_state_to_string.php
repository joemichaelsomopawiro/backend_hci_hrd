<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('episodes')) return;
        if (!Schema::hasColumn('episodes', 'current_workflow_state')) return;

        // Avoid enum/set truncation issues as new workflow states evolve.
        DB::statement("ALTER TABLE `episodes` MODIFY COLUMN `current_workflow_state` VARCHAR(50) NULL");
    }

    public function down(): void
    {
        // Intentionally no-op: we don't know the original enum definition.
    }
};

