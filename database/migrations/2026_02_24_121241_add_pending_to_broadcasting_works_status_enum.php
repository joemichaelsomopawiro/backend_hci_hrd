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
        DB::statement("ALTER TABLE broadcasting_works MODIFY COLUMN status ENUM('pending', 'pending_approval', 'preparing', 'uploading', 'processing', 'published', 'scheduled', 'failed', 'cancelled', 'rejected') DEFAULT 'preparing'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE broadcasting_works MODIFY COLUMN status ENUM('pending_approval', 'preparing', 'uploading', 'processing', 'published', 'scheduled', 'failed', 'cancelled', 'rejected') DEFAULT 'preparing'");
    }
};
