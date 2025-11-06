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
        // Check if table exists and add missing columns
        if (Schema::hasTable('design_grafis_works')) {
            Schema::table('design_grafis_works', function (Blueprint $table) {
                // Add title column if it doesn't exist
                if (!Schema::hasColumn('design_grafis_works', 'title')) {
                    $table->string('title')->after('work_type');
                }
                
                // Add other missing columns if they don't exist
                if (!Schema::hasColumn('design_grafis_works', 'description')) {
                    $table->text('description')->nullable()->after('title');
                }
                
                if (!Schema::hasColumn('design_grafis_works', 'design_brief')) {
                    $table->text('design_brief')->nullable()->after('description');
                }
                
                if (!Schema::hasColumn('design_grafis_works', 'brand_guidelines')) {
                    $table->text('brand_guidelines')->nullable()->after('design_brief');
                }
                
                if (!Schema::hasColumn('design_grafis_works', 'color_scheme')) {
                    $table->string('color_scheme')->nullable()->after('brand_guidelines');
                }
                
                if (!Schema::hasColumn('design_grafis_works', 'dimensions')) {
                    $table->string('dimensions')->nullable()->after('color_scheme');
                }
                
                if (!Schema::hasColumn('design_grafis_works', 'file_format')) {
                    $table->string('file_format')->nullable()->after('dimensions');
                }
                
                if (!Schema::hasColumn('design_grafis_works', 'deadline')) {
                    $table->date('deadline')->nullable()->after('file_format');
                }
                
                if (!Schema::hasColumn('design_grafis_works', 'file_paths')) {
                    $table->json('file_paths')->nullable()->after('mime_type');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove added columns if they exist
        if (Schema::hasTable('design_grafis_works')) {
            Schema::table('design_grafis_works', function (Blueprint $table) {
                $columns = [
                    'title', 'description', 'design_brief', 'brand_guidelines',
                    'color_scheme', 'dimensions', 'file_format', 'deadline', 'file_paths'
                ];
                
                foreach ($columns as $column) {
                    if (Schema::hasColumn('design_grafis_works', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
