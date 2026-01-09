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
        // Check if table already exists
        if (!Schema::hasTable('design_grafis_works')) {
            Schema::create('design_grafis_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('music_submissions')->onDelete('cascade');
            $table->string('design_title');
            $table->text('description');
            $table->enum('design_type', ['poster', 'thumbnail', 'banner', 'logo', 'social_media', 'print_material', 'digital_asset', 'other']);
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['requested', 'in_progress', 'review', 'revision', 'completed', 'approved'])->default('requested');
            $table->text('requirements')->nullable();
            $table->text('brand_guidelines')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->integer('estimated_hours')->nullable();
            $table->integer('actual_hours')->nullable();
            $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('design_grafis_works');
    }
};
