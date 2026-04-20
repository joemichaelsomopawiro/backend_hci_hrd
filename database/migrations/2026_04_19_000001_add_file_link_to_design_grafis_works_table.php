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
        if (Schema::hasTable("design_grafis_works")) {
            Schema::table("design_grafis_works", function (Blueprint $table) {
                if (!Schema::hasColumn("design_grafis_works", "file_link")) {
                    $table->string("file_link")->nullable()->after("design_notes");
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable("design_grafis_works")) {
            Schema::table("design_grafis_works", function (Blueprint $table) {
                if (Schema::hasColumn("design_grafis_works", "file_link")) {
                    $table->dropColumn("file_link");
                }
            });
        }
    }
};
