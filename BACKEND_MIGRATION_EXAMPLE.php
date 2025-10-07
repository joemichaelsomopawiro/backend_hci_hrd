<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('programs_reguler', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['regular', 'special', 'seasonal'])->default('regular');
            $table->enum('status', ['draft', 'planning', 'production', 'completed', 'archived'])->default('draft');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('air_time', 50);
            $table->integer('duration_minutes');
            $table->string('broadcast_channel', 100);
            $table->text('description')->nullable();
            $table->string('target_audience')->nullable();
            $table->decimal('budget', 15, 2)->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('manager_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['status', 'type']);
            $table->index('start_date');
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('role', ['kreatif', 'promosi', 'design_grafis', 'produksi', 'art_set_properti', 'editor', 'producer'])->nullable();
            $table->unsignedBigInteger('program_id')->nullable();
            $table->unsignedBigInteger('team_lead_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('program_id')->references('id')->on('programs_reguler')->onDelete('cascade');
            $table->foreign('team_lead_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['program_id', 'is_active']);
            $table->index('role');
        });

        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('role', ['lead', 'member', 'assistant'])->default('member');
            $table->boolean('is_active')->default(true);
            $table->date('joined_date')->default(now());
            $table->date('left_date')->nullable();
            $table->timestamps();
            
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['team_id', 'user_id']);
            $table->index(['team_id', 'is_active']);
        });

        Schema::create('episodes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('program_id');
            $table->integer('episode_number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('scheduled_date')->nullable();
            $table->date('air_date')->nullable();
            $table->enum('status', ['draft', 'scripting', 'production', 'editing', 'ready', 'aired', 'archived'])->default('draft');
            $table->integer('duration_minutes')->nullable();
            $table->unsignedBigInteger('producer_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('program_id')->references('id')->on('programs_reguler')->onDelete('cascade');
            $table->foreign('producer_id')->references('id')->on('users')->onDelete('set null');
            $table->unique(['program_id', 'episode_number']);
            $table->index(['program_id', 'status']);
            $table->index('scheduled_date');
        });

        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('program_id')->nullable();
            $table->unsignedBigInteger('episode_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('scheduled_date');
            $table->time('scheduled_time');
            $table->integer('duration_minutes')->nullable();
            $table->string('location')->nullable();
            $table->enum('type', ['recording', 'editing', 'review', 'meeting', 'broadcast', 'other'])->default('recording');
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled', 'rescheduled'])->default('scheduled');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->json('participants')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('program_id')->references('id')->on('programs_reguler')->onDelete('cascade');
            $table->foreign('episode_id')->references('id')->on('episodes')->onDelete('cascade');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->index(['scheduled_date', 'status']);
            $table->index(['program_id', 'scheduled_date']);
        });

        Schema::create('workflow_states', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->string('color', 20)->default('#6B7280');
            $table->integer('order')->default(0);
            $table->boolean('is_final')->default(false);
            $table->timestamps();
        });

        Schema::create('workflow_transitions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('display_name');
            $table->unsignedBigInteger('from_state_id');
            $table->unsignedBigInteger('to_state_id');
            $table->json('required_roles')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->foreign('from_state_id')->references('id')->on('workflow_states')->onDelete('cascade');
            $table->foreign('to_state_id')->references('id')->on('workflow_states')->onDelete('cascade');
        });

        Schema::create('workflow_history', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 50);
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('from_state_id')->nullable();
            $table->unsignedBigInteger('to_state_id');
            $table->unsignedBigInteger('transition_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->foreign('from_state_id')->references('id')->on('workflow_states')->onDelete('set null');
            $table->foreign('to_state_id')->references('id')->on('workflow_states')->onDelete('cascade');
            $table->foreign('transition_id')->references('id')->on('workflow_transitions')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['entity_type', 'entity_id']);
            $table->index('created_at');
        });

        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 50);
            $table->unsignedBigInteger('entity_id');
            $table->string('category', 50);
            $table->string('original_name');
            $table->string('stored_name')->unique();
            $table->string('file_path');
            $table->string('mime_type', 100);
            $table->bigInteger('file_size');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('uploaded_by');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['entity_type', 'entity_id', 'category']);
        });

        Schema::create('art_set_properti', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('category', ['camera', 'lighting', 'audio', 'props', 'set', 'costume', 'other'])->default('other');
            $table->integer('quantity')->default(1);
            $table->enum('status', ['available', 'in_use', 'maintenance', 'damaged', 'retired'])->default('available');
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('program_id')->nullable();
            $table->date('requested_for')->nullable();
            $table->date('returned_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('requested_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->foreign('program_id')->references('id')->on('programs_reguler')->onDelete('set null');
            $table->index(['category', 'status']);
            $table->index('program_id');
        });

        Schema::create('program_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('type', 50);
            $table->string('title');
            $table->text('message');
            $table->string('entity_type', 50)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('data')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'is_read']);
            $table->index(['entity_type', 'entity_id']);
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('program_notifications');
        Schema::dropIfExists('art_set_properti');
        Schema::dropIfExists('media_files');
        Schema::dropIfExists('workflow_history');
        Schema::dropIfExists('workflow_transitions');
        Schema::dropIfExists('workflow_states');
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('episodes');
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('programs_reguler');
    }
};

