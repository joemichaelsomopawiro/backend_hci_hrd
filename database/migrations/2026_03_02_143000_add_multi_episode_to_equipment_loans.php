<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Converts EquipmentLoan from 1-to-1 with PrProduksiWork
     * to Many-to-Many via a pivot table.
     */
    public function up(): void
    {
        // 1. Create pivot table FIRST (before dropping the old FK)
        if (!Schema::hasTable('equipment_loan_produksi_work')) {
            Schema::create('equipment_loan_produksi_work', function (Blueprint $table) {
                $table->id();
                $table->foreignId('equipment_loan_id')->constrained('equipment_loans')->onDelete('cascade');
                $table->foreignId('pr_produksi_work_id')->constrained('pr_produksi_works')->onDelete('cascade');
                $table->timestamps();

                // Ensure unique combination
                $table->unique(['equipment_loan_id', 'pr_produksi_work_id'], 'loan_work_unique');
            });
        }

        // 2. Migrate existing data into pivot table BEFORE dropping the column
        if (Schema::hasColumn('equipment_loans', 'pr_produksi_work_id')) {
            $existingLoans = DB::table('equipment_loans')
                ->whereNotNull('pr_produksi_work_id')
                ->get(['id', 'pr_produksi_work_id']);

            foreach ($existingLoans as $loan) {
                DB::table('equipment_loan_produksi_work')->insertOrIgnore([
                    'equipment_loan_id' => $loan->id,
                    'pr_produksi_work_id' => $loan->pr_produksi_work_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 3. Drop the old FK column from equipment_loans
            Schema::table('equipment_loans', function (Blueprint $table) {
                // Drop FK constraint first
                $table->dropForeign(['pr_produksi_work_id']);
                $table->dropColumn('pr_produksi_work_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add the column
        Schema::table('equipment_loans', function (Blueprint $table) {
            $table->foreignId('pr_produksi_work_id')
                ->nullable()
                ->after('id')
                ->constrained('pr_produksi_works')
                ->onDelete('cascade');
        });

        // Restore first association from pivot (best-effort rollback)
        $pivotRows = DB::table('equipment_loan_produksi_work')->get();
        foreach ($pivotRows as $row) {
            DB::table('equipment_loans')
                ->where('id', $row->equipment_loan_id)
                ->whereNull('pr_produksi_work_id')
                ->update(['pr_produksi_work_id' => $row->pr_produksi_work_id]);
        }

        Schema::dropIfExists('equipment_loan_produksi_work');
    }
};
