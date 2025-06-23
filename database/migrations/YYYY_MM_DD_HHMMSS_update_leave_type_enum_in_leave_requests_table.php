<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdateLeaveTypeEnumInLeaveRequestsTable extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE leave_requests MODIFY COLUMN leave_type ENUM('annual', 'sick', 'emergency', 'maternity', 'paternity', 'marriage', 'bereavement')");
    }

    public function down()
    {
        DB::statement("ALTER TABLE leave_requests MODIFY COLUMN leave_type ENUM('annual', 'sick', 'emergency', 'maternity', 'paternity')");
    }
}