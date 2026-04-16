<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. First, expand the enum to include BOTH old and new statuses
        $combinedEnum = "'draft', 'concept_pending', 'active', 'inactive', 'deleted', 'concept_approved', 'concept_rejected', 'production_scheduled', 'in_production', 'editing', 'submitted_to_manager', 'manager_approved', 'manager_rejected', 'submitted_to_distribusi', 'distribusi_approved', 'distribusi_rejected', 'scheduled', 'distributed', 'completed', 'cancelled', 'broadcasting_complete', 'promoted'";
        DB::statement("ALTER TABLE pr_programs MODIFY COLUMN status ENUM($combinedEnum) NOT NULL DEFAULT 'draft'");

        // 2. Now map existing data to new statuses safely
        $this->migrateExistingData();

        // 3. Finally, shrink the enum to only the new statuses
        $newEnum = "'draft', 'concept_pending', 'active', 'inactive', 'deleted'";
        DB::statement("ALTER TABLE pr_programs MODIFY COLUMN status ENUM($newEnum) NOT NULL DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // To reverse, we'd need to restore the full list of statuses
        $oldEnum = "'draft','concept_pending','concept_approved','concept_rejected','production_scheduled','in_production','editing','submitted_to_manager','manager_approved','manager_rejected','submitted_to_distribusi','distribusi_approved','distribusi_rejected','scheduled','distributed','completed','cancelled','broadcasting_complete','promoted'";
        DB::statement("ALTER TABLE pr_programs MODIFY COLUMN status ENUM($oldEnum) NOT NULL DEFAULT 'draft'");
    }

    private function migrateExistingData(): void
    {
        $activeStatuses = [
            'concept_approved', 'production_scheduled', 'in_production', 'editing',
            'submitted_to_manager', 'manager_approved', 'submitted_to_distribusi',
            'distribusi_approved', 'scheduled', 'distributed', 'completed',
            'broadcasting_complete', 'promoted'
        ];
        
        $inactiveStatuses = [
            'cancelled', 'on_hold', 'concept_rejected', 'manager_rejected', 'distribusi_rejected', 'inactive'
        ];

        // Update to 'active'
        DB::table('pr_programs')
            ->whereIn('status', $activeStatuses)
            ->update(['status' => 'active']);

        // Update to 'inactive'
        DB::table('pr_programs')
            ->whereIn('status', $inactiveStatuses)
            ->update(['status' => 'inactive']);
            
        // If it's already trashed, we might want to mark it as 'deleted' status too?
        // User said: "deleted status nya deleted"
        DB::table('pr_programs')
            ->whereNotNull('deleted_at')
            ->update(['status' => 'deleted']);
    }
};
