<?php

use Illuminate\Support\Facades\Route;
use App\Models\MusicWorkflowNotification;

// Test routes untuk debugging
Route::get('/test/notifications/{id}', function ($id) {
    try {
        $notification = MusicWorkflowNotification::find($id);
        
        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
                'id' => $id
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $notification->id,
                'is_read' => $notification->is_read,
                'read_at' => $notification->read_at,
                'notification_type' => $notification->notification_type,
                'title' => $notification->title,
                'message' => $notification->message
            ]
        ]);
        
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

Route::get('/test/notifications/count', function () {
    try {
        $count = MusicWorkflowNotification::count();
        
        return response()->json([
            'success' => true,
            'data' => [
                'total_notifications' => $count
            ]
        ]);
        
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});


// ─────────────────────────────────────────────────────────────────────────────
// ONE-TIME FIX: Activity Log wrong user_id
// Bug: $work->id was passed as the 5th argument (userId) to logEpisodeActivity.
// Call: GET /api/test/fix-activity-log-users
// After running successfully, delete or comment out this route.
// ─────────────────────────────────────────────────────────────────────────────
Route::get('/test/fix-activity-log-users', function () {
    $fixed = [];

    // 1. Fix broadcasting_finish: user_id should be pr_broadcasting_works.created_by
    $broadcastingLogs = \Illuminate\Support\Facades\DB::table('pr_activity_logs as pal')
        ->join('pr_broadcasting_works as pbw', function ($join) {
            $join->on('pbw.id', '=', 'pal.user_id')
                 ->whereNotNull('pbw.created_by');
        })
        ->where('pal.action', 'broadcasting_finish')
        ->whereNull('pal.deleted_at')
        ->select('pal.id as log_id', 'pbw.id as work_id', 'pbw.created_by as correct_user_id', 'pal.user_id as wrong_user_id')
        ->get();

    foreach ($broadcastingLogs as $row) {
        \Illuminate\Support\Facades\DB::table('pr_activity_logs')
            ->where('id', $row->log_id)
            ->update(['user_id' => $row->correct_user_id]);
        $fixed[] = [
            'log_id'          => $row->log_id,
            'action'          => 'broadcasting_finish',
            'wrong_user_id'   => $row->wrong_user_id,
            'correct_user_id' => $row->correct_user_id,
        ];
    }

    // 2. Fix Manager Distribusi QC logs (qc_revision, qc_item_approved, qc_finish)
    $qdActions = ['qc_revision', 'qc_item_approved', 'qc_finish'];
    $qdLogs = \Illuminate\Support\Facades\DB::table('pr_activity_logs as pal')
        ->join('pr_manager_distribusi_qc_works as qw', function ($join) {
            $join->on('qw.id', '=', 'pal.user_id')
                 ->whereNotNull('qw.reviewed_by');
        })
        ->whereIn('pal.action', $qdActions)
        ->whereNull('pal.deleted_at')
        ->select('pal.id as log_id', 'qw.reviewed_by as correct_user_id', 'pal.user_id as wrong_user_id', 'pal.action')
        ->get();

    foreach ($qdLogs as $row) {
        \Illuminate\Support\Facades\DB::table('pr_activity_logs')
            ->where('id', $row->log_id)
            ->update(['user_id' => $row->correct_user_id]);
        $fixed[] = [
            'log_id'          => $row->log_id,
            'action'          => $row->action,
            'wrong_user_id'   => $row->wrong_user_id,
            'correct_user_id' => $row->correct_user_id,
        ];
    }

    return response()->json([
        'success'     => true,
        'fixed_count' => count($fixed),
        'fixed'       => $fixed,
        'message'     => count($fixed) > 0
            ? count($fixed) . ' record(s) corrected. You can now remove this route from test_api.php.'
            : 'No records needed fixing (all already correct).',
    ]);
});
