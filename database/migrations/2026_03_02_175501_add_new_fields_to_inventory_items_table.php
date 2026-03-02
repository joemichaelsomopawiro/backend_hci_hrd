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
            $table->string('equipment_id', 50)->unique()->nullable()->after('id');
            $table->string('condition')->nullable()->after('available_quantity');
            $table->string('location')->nullable()->after('condition');
            $table->string('position')->nullable()->after('location');
            $table->string('category')->nullable()->after('position');
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
