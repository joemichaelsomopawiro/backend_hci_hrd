<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DebugEnum extends Command
{
    protected $signature = 'debug:enum';
    protected $description = 'Debug Enum Column';

    public function handle()
    {
        $result = DB::select("SHOW COLUMNS FROM sound_engineer_editing WHERE Field = 'status'");
        if (!empty($result)) {
            $this->info("Type: " . $result[0]->Type);
        } else {
            $this->error("Column not found");
        }
    }
}
