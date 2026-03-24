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
            if (!Schema::hasColumn('production_equipment', 'request_group_id')) {
                $table->string('request_group_id', 64)->nullable()->after('program_id');
                $table->index('request_group_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('production_equipment')) {
            return;
        }

        Schema::table('production_equipment', function (Blueprint $table) {
            if (Schema::hasColumn('production_equipment', 'request_group_id')) {
                $table->dropIndex(['request_group_id']);
                $table->dropColumn('request_group_id');
            }
        });
    }
};

