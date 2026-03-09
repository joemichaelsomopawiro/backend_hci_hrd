<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Add 'cancelled' to the status ENUM column
        DB::statement("ALTER TABLE equipment_loans MODIFY COLUMN status ENUM('pending','active','return_requested','returned','completed','cancelled') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Remove 'cancelled' from status ENUM (revert)
        DB::statement("ALTER TABLE equipment_loans MODIFY COLUMN status ENUM('pending','active','return_requested','returned','completed') NOT NULL DEFAULT 'pending'");
    }
};
