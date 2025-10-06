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
        Schema::table('production_equipment', function (Blueprint $table) {
            $table->enum('status', [
                'available', 
                'requested', 
                'approved',
                'rejected',
                'assigned', 
                'in_use', 
                'maintenance', 
                'broken',
                'returned'
            ])->default('available')->change();
            
            $table->enum('category', [
                'camera', 
                'lighting', 
                'audio', 
                'props', 
                'set_design', 
                'other'
            ])->change();
            
            $table->timestamp('requested_for_date')->nullable();
            $table->timestamp('return_date')->nullable();
            $table->text('approval_notes')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->text('assignment_notes')->nullable();
            $table->enum('return_condition', ['good', 'damaged', 'needs_maintenance'])->nullable();
            $table->text('return_notes')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->unsignedBigInteger('returned_by')->nullable();
            
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('rejected_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('returned_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_equipment', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['rejected_by']);
            $table->dropForeign(['returned_by']);
            
            $table->dropColumn([
                'requested_for_date',
                'return_date',
                'approval_notes',
                'approved_by',
                'approved_at',
                'rejection_reason',
                'rejected_by',
                'rejected_at',
                'assigned_at',
                'assignment_notes',
                'return_condition',
                'return_notes',
                'returned_at',
                'returned_by'
            ]);
            
            $table->enum('status', ['available', 'assigned', 'in_use', 'maintenance', 'broken'])->default('available')->change();
            $table->enum('category', ['camera', 'lighting', 'audio', 'props', 'other'])->change();
        });
    }
};
