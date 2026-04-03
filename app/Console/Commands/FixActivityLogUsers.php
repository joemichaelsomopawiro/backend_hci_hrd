<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixActivityLogUsers extends Command
{
    protected $signature   = 'pr:fix-activity-log-users';
    protected $description = 'Fix activity log records where $work->id was mistakenly used as user_id';

    public function handle(): int
    {
        $this->info('Starting activity log user fix...');
        $totalFixed = 0;

        // ── 1. Fix broadcasting_finish logs ─────────────────────────────────
        // Bug: user_id was set to pr_broadcasting_works.id instead of Auth::id()
        // Fix: use pr_broadcasting_works.created_by (which WAS correctly set to Auth::id())
        $broadcastingLogs = DB::table('pr_activity_logs as pal')
            ->join('pr_broadcasting_works as pbw', function ($join) {
                $join->on('pbw.id', '=', 'pal.user_id')        // wrong user_id = work id
                     ->whereNotNull('pbw.created_by');
            })
            ->where('pal.action', 'broadcasting_finish')
            ->whereNull('pal.deleted_at')
            ->select('pal.id as log_id', 'pbw.id as work_id', 'pbw.created_by as correct_user_id', 'pal.user_id as wrong_user_id')
            ->get();

        foreach ($broadcastingLogs as $row) {
            DB::table('pr_activity_logs')
                ->where('id', $row->log_id)
                ->update(['user_id' => $row->correct_user_id]);

            $this->line(sprintf(
                "  [broadcasting_finish] log #%d: user_id %d → %d (work #%d)",
                $row->log_id,
                $row->wrong_user_id,
                $row->correct_user_id,
                $row->work_id
            ));
            $totalFixed++;
        }

        // ── 2. Fix Manager Distribusi QC logs ───────────────────────────────
        // Bug: user_id was set to pr_manager_distribusi_qc_works.id
        // Fix: use reviewed_by (correctly captured from Auth::user())
        $qdActions = ['qc_revision', 'qc_item_approved', 'qc_finish'];

        $qdLogs = DB::table('pr_activity_logs as pal')
            ->join('pr_manager_distribusi_qc_works as qw', function ($join) {
                $join->on('qw.id', '=', 'pal.user_id')
                     ->whereNotNull('qw.reviewed_by');
            })
            ->whereIn('pal.action', $qdActions)
            ->whereNull('pal.deleted_at')
            ->select('pal.id as log_id', 'qw.id as work_id', 'qw.reviewed_by as correct_user_id', 'pal.user_id as wrong_user_id', 'pal.action')
            ->get();

        foreach ($qdLogs as $row) {
            DB::table('pr_activity_logs')
                ->where('id', $row->log_id)
                ->update(['user_id' => $row->correct_user_id]);

            $this->line(sprintf(
                "  [%s] log #%d: user_id %d → %d (work #%d)",
                $row->action,
                $row->log_id,
                $row->wrong_user_id,
                $row->correct_user_id,
                $row->work_id
            ));
            $totalFixed++;
        }

        // ── 3. Fallback: any remaining logs where user_id does NOT exist in users ─
        // Safety net for any other controller that had the same bug
        $orphanLogs = DB::table('pr_activity_logs as pal')
            ->leftJoin('users as u', 'u.id', '=', 'pal.user_id')
            ->whereNull('u.id')           // user not found → wrong id
            ->whereNotNull('pal.user_id')
            ->whereNull('pal.deleted_at')
            ->select('pal.id', 'pal.user_id', 'pal.action')
            ->get();

        if ($orphanLogs->isNotEmpty()) {
            $this->warn('');
            $this->warn('The following logs still have invalid user_ids (no matching user in DB):');
            foreach ($orphanLogs as $log) {
                $this->warn(sprintf(
                    "  log #%d  action=%s  user_id=%d (no user found)",
                    $log->id,
                    $log->action,
                    $log->user_id
                ));
            }
        }

        $this->info('');
        $this->info("Done! Fixed $totalFixed log record(s).");

        return 0;
    }
}
