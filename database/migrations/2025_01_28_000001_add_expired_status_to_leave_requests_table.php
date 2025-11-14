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
        // Check if table exists
        if (!Schema::hasTable('leave_requests')) {
            return;
        }
        
        // Check if column exists
        if (!Schema::hasColumn('leave_requests', 'overall_status')) {
            return;
        }
        
        try {
            // Get current enum values
            $result = DB::select("SHOW COLUMNS FROM leave_requests WHERE Field = 'overall_status'");
            if (!empty($result)) {
                $currentType = $result[0]->Type;
                // Check if 'expired' is already in the enum
                if (strpos($currentType, 'expired') === false) {
                    Schema::table('leave_requests', function (Blueprint $table) {
                        // Tambahkan status 'expired' ke enum overall_status
                        $table->enum('overall_status', ['pending', 'approved', 'rejected', 'expired'])
                              ->default('pending')
                              ->change();
                    });
                }
            }
        } catch (\Exception $e) {
            // Enum change failed, skip
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            // Kembalikan ke enum tanpa 'expired'
            $table->enum('overall_status', ['pending', 'approved', 'rejected'])
                  ->default('pending')
                  ->change();
        });
    }
};