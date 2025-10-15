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
        Schema::create('art_set_properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('music_submissions')->onDelete('cascade');
            $table->string('property_name');
            $table->text('description');
            $table->enum('category', ['furniture', 'decoration', 'lighting', 'props', 'costume', 'other']);
            $table->decimal('cost', 15, 2)->nullable();
            $table->string('supplier')->nullable();
            $table->enum('status', ['requested', 'approved', 'purchased', 'delivered', 'returned'])->default('requested');
            $table->text('notes')->nullable();
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('art_set_properties');
    }
};
