<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    $query = DB::table('pr_episodes as e')
        ->join('pr_programs as p', 'e.program_id', '=', 'p.id')
        ->leftJoin('pr_creative_works as cw', 'e.id', '=', 'cw.pr_episode_id')
        ->leftJoin('users as creator', 'cw.created_by', '=', 'creator.id')
        ->select(
            'cw.id',
            'p.name as program_name',
            'e.id as episode_id',
            'e.episode_number',
            'e.title as episode_title',
            'cw.total_budget',
            'cw.budget_data',
            'cw.status',
            'cw.requires_special_budget_approval as needs_pm_approval',
            'cw.special_budget_approved_at as pm_approved_at',
            'cw.created_at as requested_at',
            'creator.name as requested_by_name',
            'p.max_budget_per_episode as max_budget'
        )
        ->where('cw.requires_special_budget_approval', true)
        ->whereNull('p.deleted_at');

    $history = $query->orderBy('cw.created_at', 'desc')->get();
    file_put_contents('error.txt', "SUCCESS\nCount: " . count($history) . "\n");
} catch (\Exception $e) {
    file_put_contents('error.txt', "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString());
}
