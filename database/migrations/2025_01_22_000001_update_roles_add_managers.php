<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop existing role column
            $table->dropColumn('role');
        });
        
        Schema::table('users', function (Blueprint $table) {
            // Add new role with manager hierarchy
            $table->enum('role', [
                'HR', 'Program Manager', 'Distribution Manager', 'GA',
                'Finance', 'General Affairs', 'Office Assistant',
                'Producer', 'Creative', 'Production', 'Editor',
                'Social Media', 'Promotion', 'Graphic Design', 'Hopeline Care',
                'Employee'
            ])->default('Employee')->after('employee_id');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
        
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['HR', 'Manager', 'Employee', 'GA'])->default('Employee')->after('employee_id');
        });
    }
};