<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Inventory Items (Master Inventory)
        if (!Schema::hasTable('inventory_items')) {
            Schema::create('inventory_items', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('photo_url')->nullable();
                $table->integer('total_quantity')->default(0);
                $table->integer('available_quantity')->default(0);
                $table->enum('status', ['active', 'maintenance', 'lost', 'damaged'])->default('active');
                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
                $table->softDeletes();
                $table->timestamps();
            });
        }

        // 2. Equipment Loans (Loan Requests)
        if (!Schema::hasTable('equipment_loans')) {
            Schema::create('equipment_loans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pr_produksi_work_id')->constrained('pr_produksi_works')->onDelete('cascade');
                $table->foreignId('borrower_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('approver_id')->nullable()->constrained('users')->onDelete('set null');
                $table->enum('status', ['pending', 'approved', 'active', 'returned', 'rejected', 'overdue'])->default('pending');
                $table->dateTime('loan_date')->nullable(); // When picked up
                $table->dateTime('return_date')->nullable(); // When returned
                $table->text('request_notes')->nullable();
                $table->text('approval_notes')->nullable();
                $table->text('return_notes')->nullable();
                $table->timestamps();
            });
        }

        // 3. Equipment Loan Items (Items inside a loan)
        if (!Schema::hasTable('equipment_loan_items')) {
            Schema::create('equipment_loan_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('equipment_loan_id')->constrained('equipment_loans')->onDelete('cascade');
                $table->foreignId('inventory_item_id')->constrained('inventory_items')->onDelete('restrict');
                $table->integer('quantity');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_loan_items');
        Schema::dropIfExists('equipment_loans');
        Schema::dropIfExists('inventory_items');
    }
};
