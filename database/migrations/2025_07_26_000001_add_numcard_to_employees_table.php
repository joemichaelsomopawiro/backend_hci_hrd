<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Check if table exists
        if (!Schema::hasTable('employees')) {
            return;
        }
        
        // Check if column already exists
        if (!Schema::hasColumn('employees', 'NumCard')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('NumCard', 10)->nullable()->unique()->after('nip')->comment('Nomor kartu absensi 10 digit');
            });
        }
    }

    public function down()
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('NumCard');
        });
    }
}; 