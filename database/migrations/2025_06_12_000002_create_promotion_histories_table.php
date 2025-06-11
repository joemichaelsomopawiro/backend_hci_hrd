<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePromotionHistoriesTable extends Migration
{
    public function up()
    {
        Schema::create('promotion_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->string('position', 100);
            $table->date('promotion_date');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('promotion_histories');
    }
}