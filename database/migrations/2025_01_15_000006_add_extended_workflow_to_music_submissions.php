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
        Schema::table('music_submissions', function (Blueprint $table) {
            // Extended workflow states
            $table->enum('current_state', [
                'submitted', 'producer_review', 'arranging', 'arrangement_review',
                'sound_engineering', 'quality_control', 'creative_work', 'creative_review',
                'producer_final_review', 'manager_approval', 'general_affairs', 'promotion',
                'production', 'sound_engineering_final', 'final_approval', 'completed', 'rejected'
            ])->change();
            
            // Creative work review data
            $table->text('creative_review_notes')->nullable()->after('budget_data');
            $table->boolean('creative_approved')->default(false)->after('creative_review_notes');
            
            // Producer final review data
            $table->text('producer_final_notes')->nullable()->after('creative_approved');
            $table->json('shooting_crew')->nullable()->after('producer_final_notes');
            $table->json('setting_crew')->nullable()->after('shooting_crew');
            $table->json('vocal_recording_crew')->nullable()->after('setting_crew');
            $table->boolean('shooting_cancelled')->default(false)->after('vocal_recording_crew');
            $table->text('shooting_cancel_reason')->nullable()->after('shooting_cancelled');
            $table->json('additional_budget')->nullable()->after('shooting_cancel_reason');
            
            // Manager approval data
            $table->text('manager_notes')->nullable()->after('additional_budget');
            $table->boolean('manager_approved')->default(false)->after('manager_notes');
            $table->decimal('approved_budget', 15, 2)->nullable()->after('manager_approved');
            
            // General affairs data
            $table->text('general_affairs_notes')->nullable()->after('approved_budget');
            $table->boolean('funds_released')->default(false)->after('general_affairs_notes');
            $table->timestamp('funds_released_at')->nullable()->after('funds_released');
            
            // Promotion data
            $table->text('promotion_notes')->nullable()->after('funds_released_at');
            $table->string('bts_video_path')->nullable()->after('promotion_notes');
            $table->string('bts_video_url')->nullable()->after('bts_video_path');
            $table->string('talent_photos_path')->nullable()->after('bts_video_url');
            $table->string('talent_photos_url')->nullable()->after('talent_photos_path');
            $table->boolean('promotion_completed')->default(false)->after('talent_photos_url');
            
            // Production data
            $table->text('production_notes')->nullable()->after('promotion_completed');
            $table->json('equipment_request')->nullable()->after('production_notes');
            $table->json('equipment_approved')->nullable()->after('equipment_request');
            $table->boolean('production_completed')->default(false)->after('equipment_approved');
            
            // Sound engineering final data
            $table->text('sound_engineering_final_notes')->nullable()->after('production_completed');
            $table->json('sound_equipment_request')->nullable()->after('sound_engineering_final_notes');
            $table->json('sound_equipment_approved')->nullable()->after('sound_equipment_request');
            $table->boolean('sound_engineering_final_completed')->default(false)->after('sound_equipment_approved');
            
            // Add indexes for new workflow
            $table->index(['current_state', 'created_at']);
            $table->index(['manager_approved', 'created_at']);
            $table->index(['funds_released', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('music_submissions', function (Blueprint $table) {
            $table->dropIndex(['current_state', 'created_at']);
            $table->dropIndex(['manager_approved', 'created_at']);
            $table->dropIndex(['funds_released', 'created_at']);
            
            $table->dropColumn([
                'creative_review_notes', 'creative_approved', 'producer_final_notes',
                'shooting_crew', 'setting_crew', 'vocal_recording_crew',
                'shooting_cancelled', 'shooting_cancel_reason', 'additional_budget',
                'manager_notes', 'manager_approved', 'approved_budget',
                'general_affairs_notes', 'funds_released', 'funds_released_at',
                'promotion_notes', 'bts_video_path', 'bts_video_url',
                'talent_photos_path', 'talent_photos_url', 'promotion_completed',
                'production_notes', 'equipment_request', 'equipment_approved', 'production_completed',
                'sound_engineering_final_notes', 'sound_equipment_request',
                'sound_equipment_approved', 'sound_engineering_final_completed'
            ]);
        });
    }
};














