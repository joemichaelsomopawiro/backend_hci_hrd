<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Laporan Distribusi - dibuat oleh Manager Distribusi
     */
    public function up(): void
    {
        Schema::create('pr_distribution_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('pr_programs')->onDelete('cascade');
            $table->foreignId('episode_id')->nullable()->constrained('pr_episodes')->onDelete('cascade');
            
            // Report Information
            $table->string('report_title'); // Judul laporan
            $table->text('report_content'); // Isi laporan
            
            // Distribution Data
            $table->json('distribution_data')->nullable(); // Data distribusi (platform, views, dll)
            $table->json('analytics_data')->nullable(); // Data analytics (views, engagement, dll)
            
            // Report Period
            $table->date('report_period_start')->nullable(); // Periode laporan mulai
            $table->date('report_period_end')->nullable(); // Periode laporan akhir
            
            // Status
            $table->enum('status', [
                'draft',              // Draft
                'published',          // Dipublikasikan
                'archived'            // Diarsipkan
            ])->default('draft');
            
            // Created by (Manager Distribusi)
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['program_id', 'status'], 'pr_dist_reports_program_status_idx');
            $table->index(['episode_id', 'status'], 'pr_dist_reports_episode_status_idx');
            $table->index(['report_period_start', 'report_period_end'], 'pr_dist_reports_period_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pr_distribution_reports');
    }
};
