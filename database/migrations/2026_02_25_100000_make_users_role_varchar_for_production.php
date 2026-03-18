<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Sebelumnya migrasi add_singer_role menimpa ENUM users.role sehingga
     * 'Production', 'Produksi', 'Editor' hilang. Dengan VARCHAR(80), semua
     * nilai role (Inggris/Indonesia) bisa disimpan dan query getAvailableUsersForRole
     * bisa mengembalikan user Production/Produksi.
     */
    public function up(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'role')) {
            return;
        }

        DB::statement("ALTER TABLE users MODIFY COLUMN role VARCHAR(80) DEFAULT 'Employee'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'role')) {
            return;
        }

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM(
            'HR', 'Manager', 'Employee', 'GA', 'Singer', 'Creative', 'Producer',
            'Social Media', 'Music Arranger', 'Program Manager', 'Hopeline Care',
            'Distribution Manager', 'Sound Engineer', 'General Affairs',
            'Production', 'Produksi', 'Editor'
        ) DEFAULT 'Employee'");
    }
};
