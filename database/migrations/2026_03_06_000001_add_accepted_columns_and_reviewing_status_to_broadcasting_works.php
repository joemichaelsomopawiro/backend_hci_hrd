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
        Schema::table('broadcasting_works', function (Blueprint $table) {
            $table->foreignId('accepted_by')->after('status')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('accepted_at')->after('accepted_by')->nullable();
        });

        // Update status enum to include 'reviewing'
        DB::statement("ALTER TABLE broadcasting_works MODIFY COLUMN status ENUM('pending', 'pending_approval', 'reviewing', 'preparing', 'uploading', 'processing', 'published', 'scheduled', 'failed', 'cancelled', 'rejected') DEFAULT 'preparing'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('broadcasting_works', function (Blueprint $table) {
            $table->dropForeign(['accepted_by']);
            $table->dropColumn(['accepted_by', 'accepted_at']);
        });

        DB::statement("ALTER TABLE broadcasting_works MODIFY COLUMN status ENUM('pending', 'pending_approval', 'preparing', 'uploading', 'processing', 'published', 'scheduled', 'failed', 'cancelled', 'rejected') DEFAULT 'preparing'");
    }
};
