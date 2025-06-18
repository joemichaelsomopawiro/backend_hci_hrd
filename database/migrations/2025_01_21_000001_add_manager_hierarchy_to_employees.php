<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->enum('department', [
                'Finance', 'General Affairs', 'Office Assistant',
                'Producer', 'Creative', 'Production', 'Editor',
                'Social Media', 'Promotion', 'Graphic Design', 'Hopeline Care'
            ])->nullable()->after('jabatan_saat_ini');
            $table->unsignedBigInteger('manager_id')->nullable()->after('department');
            $table->foreign('manager_id')->references('id')->on('employees')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
            $table->dropColumn(['department', 'manager_id']);
        });
    }
};