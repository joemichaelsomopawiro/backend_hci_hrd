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
        DB::statement("ALTER TABLE equipment_loans MODIFY COLUMN status ENUM('pending','approved','active','return_requested','returned','completed','cancelled') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE equipment_loans MODIFY COLUMN status ENUM('pending','active','return_requested','returned','completed','cancelled') NOT NULL DEFAULT 'pending'");
    }
};
