<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add tracking columns for equipment loans to equipment_inventory.
     */
    public function up(): void
    {
        Schema::table('equipment_inventory', function (Blueprint $table) {
            if (!Schema::hasColumn('equipment_inventory', 'assigned_to')) {
                $table->unsignedBigInteger('assigned_to')->nullable()->after('is_active');
            }
            if (!Schema::hasColumn('equipment_inventory', 'assigned_by')) {
                $table->unsignedBigInteger('assigned_by')->nullable()->after('assigned_to');
            }
            if (!Schema::hasColumn('equipment_inventory', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('assigned_by');
            }
            if (!Schema::hasColumn('equipment_inventory', 'episode_id')) {
                $table->unsignedBigInteger('episode_id')->nullable()->after('assigned_at');
            }
            if (!Schema::hasColumn('equipment_inventory', 'return_date')) {
                $table->date('return_date')->nullable()->after('episode_id');
            }
            if (!Schema::hasColumn('equipment_inventory', 'returned_at')) {
                $table->timestamp('returned_at')->nullable()->after('return_date');
            }
            if (!Schema::hasColumn('equipment_inventory', 'return_condition')) {
                $table->string('return_condition')->nullable()->after('returned_at');
            }
            if (!Schema::hasColumn('equipment_inventory', 'return_notes')) {
                $table->text('return_notes')->nullable()->after('return_condition');
            }
            if (!Schema::hasColumn('equipment_inventory', 'notes')) {
                $table->text('notes')->nullable()->after('return_notes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipment_inventory', function (Blueprint $table) {
            $columns = [
                'assigned_to', 'assigned_by', 'assigned_at',
                'episode_id', 'return_date', 'returned_at',
                'return_condition', 'return_notes', 'notes'
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn('equipment_inventory', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
