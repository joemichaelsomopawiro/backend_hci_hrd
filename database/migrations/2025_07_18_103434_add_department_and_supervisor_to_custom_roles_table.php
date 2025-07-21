<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('custom_roles', function (Blueprint $table) {
            $table->enum('department', ['hr', 'production', 'distribution', 'executive'])->nullable()->after('access_level');
            $table->unsignedBigInteger('supervisor_id')->nullable()->after('department');
            
            $table->foreign('supervisor_id')->references('id')->on('custom_roles')->onDelete('set null');
            $table->index(['department', 'is_active']);
            $table->index('supervisor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_roles', function (Blueprint $table) {
            $table->dropForeign(['supervisor_id']);
            $table->dropIndex(['department', 'is_active']);
            $table->dropIndex(['supervisor_id']);
            $table->dropColumn(['department', 'supervisor_id']);
        });
    }
};
