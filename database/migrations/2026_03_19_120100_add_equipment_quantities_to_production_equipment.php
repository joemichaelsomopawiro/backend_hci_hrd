<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('production_equipment')) {
            return;
        }

        Schema::table('production_equipment', function (Blueprint $table) {
            if (!Schema::hasColumn('production_equipment', 'equipment_quantities')) {
                // JSON object: { "Camera": 2, "Light": 1 }
                $table->json('equipment_quantities')->nullable()->after('equipment_list');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('production_equipment')) {
            return;
        }

        Schema::table('production_equipment', function (Blueprint $table) {
            if (Schema::hasColumn('production_equipment', 'equipment_quantities')) {
                $table->dropColumn('equipment_quantities');
            }
        });
    }
};

