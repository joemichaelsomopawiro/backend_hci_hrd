<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNewLeaveTypesToExistingQuotas extends Migration
{
    public function up()
    {
        Schema::table('leave_quotas', function (Blueprint $table) {
            $table->integer('maternity_leave_quota')->default(90)->after('emergency_leave_used');
            $table->integer('maternity_leave_used')->default(0)->after('maternity_leave_quota');
            $table->integer('paternity_leave_quota')->default(7)->after('maternity_leave_used');
            $table->integer('paternity_leave_used')->default(0)->after('paternity_leave_quota');
            $table->integer('marriage_leave_quota')->default(3)->after('paternity_leave_used');
            $table->integer('marriage_leave_used')->default(0)->after('marriage_leave_quota');
            $table->integer('bereavement_leave_quota')->default(3)->after('marriage_leave_used');
            $table->integer('bereavement_leave_used')->default(0)->after('bereavement_leave_quota');
        });
    }

    public function down()
    {
        Schema::table('leave_quotas', function (Blueprint $table) {
            $table->dropColumn([
                'maternity_leave_quota',
                'maternity_leave_used',
                'paternity_leave_quota', 
                'paternity_leave_used',
                'marriage_leave_quota',
                'marriage_leave_used',
                'bereavement_leave_quota',
                'bereavement_leave_used'
            ]);
        });
    }
}