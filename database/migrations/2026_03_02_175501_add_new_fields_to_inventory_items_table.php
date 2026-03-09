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
        Schema::table('inventory_items', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_items', 'equipment_id')) {
                $table->string('equipment_id', 50)->unique()->nullable()->after('id');
            }
            if (!Schema::hasColumn('inventory_items', 'condition')) {
                $table->string('condition')->nullable()->after('available_quantity');
            }
            if (!Schema::hasColumn('inventory_items', 'location')) {
                $table->string('location')->nullable()->after('condition');
            }
            if (!Schema::hasColumn('inventory_items', 'position')) {
                $table->string('position')->nullable()->after('location');
            }
            if (!Schema::hasColumn('inventory_items', 'category')) {
                $table->string('category')->nullable()->after('position');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn(['equipment_id', 'condition', 'location', 'position', 'category']);
        });
    }
};
