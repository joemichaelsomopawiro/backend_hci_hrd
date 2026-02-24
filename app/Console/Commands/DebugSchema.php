<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\PrEditorPromosiWork;

class DebugSchema extends Command
{
    protected $signature = 'debug:schema';
    protected $description = 'Debug Database Schema';

    public function handle()
    {
        $buffer = "";

        $tables = [
            'pr_quality_control_works',
            'pr_editor_promosi_works',
            'pr_design_grafis_works',
            'design_grafis_works',
            'editor_promosi_works'
        ];

        foreach ($tables as $table) {
            $buffer .= "Schema for $table:\n";
            try {
                $columns = DB::select("SHOW COLUMNS FROM $table");
                foreach ($columns as $col) {
                    $col = (array) $col;
                    $field = $col['Field'] ?? $col['field'] ?? 'unknown';
                    $type = $col['Type'] ?? $col['type'] ?? 'unknown';
                    $buffer .= "$field ($type)\n";
                }
            } catch (\Exception $e) {
                $buffer .= "Error: " . $e->getMessage() . "\n";
            }
            $buffer .= "\n";
        }

        $buffer .= "\nPrEditorPromosiWork Table: " . (new PrEditorPromosiWork)->getTable() . "\n";

        file_put_contents(base_path('schema_log.txt'), $buffer);
        $this->info("Schema exported to schema_log.txt");
    }
}
