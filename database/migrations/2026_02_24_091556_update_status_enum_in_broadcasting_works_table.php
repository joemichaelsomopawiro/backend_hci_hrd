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
        // Using raw SQL because enum change is not well supported by Blueprint change() in older Laravel/Doctrine versions
        DB::statement("ALTER TABLE broadcasting_works MODIFY COLUMN status ENUM('pending_approval', 'preparing', 'uploading', 'processing', 'published', 'scheduled', 'failed', 'cancelled', 'rejected') DEFAULT 'preparing'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE broadcasting_works MODIFY COLUMN status ENUM('preparing', 'uploading', 'processing', 'published', 'scheduled', 'failed', 'cancelled') DEFAULT 'preparing'");
    }
};
