<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BroadcastingSchedule;
use App\Models\PromotionMaterial;
use App\Models\QualityControl;
use App\Models\Program;
use App\Models\Episode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DistributionManagerController extends Controller
{
    /**
     * Get all schedules for Distribution Manager
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Distribution Manager') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $query = BroadcastingSchedule::with(['episode.program', 'createdBy']);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by platform
            if ($request->has('platform')) {
                $query->where('platform', $request->platform);
            }

            // Filter by date range
            if ($request->has('start_date')) {
                $query->where('schedule_date', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->where('schedule_date', '<=', $request->end_date);
            }

            $schedules = $query->orderBy('schedule_date', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $schedules,
                'message' => 'Schedules retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving schedules: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get schedule by ID
     */
    public function show(string $id): JsonResponse
    {
        try {
            $schedule = BroadcastingSchedule::with(['episode.program', 'createdBy'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $schedule,
                'message' => 'Schedule retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve schedule
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Distribution Manager') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $schedule = BroadcastingSchedule::findOrFail($id);

            if ($schedule->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending schedules can be approved.'
                ], 400);
            }

            $schedule->update([
                'status' => 'scheduled',
                'approved_by' => $user->id,
                'approved_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'data' => $schedule->load(['episode.program', 'createdBy']),
                'message' => 'Schedule approved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject schedule
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Distribution Manager') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $schedule = BroadcastingSchedule::findOrFail($id);

            if ($schedule->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending schedules can be rejected.'
                ], 400);
            }

            $schedule->update([
                'status' => 'failed',
                'rejection_reason' => $request->get('reason', 'Rejected by Distribution Manager'),
                'rejected_by' => $user->id,
                'rejected_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'data' => $schedule->load(['episode.program', 'createdBy']),
                'message' => 'Schedule rejected successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all promotion materials
     */
    public function promotionMaterials(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Distribution Manager') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $query = PromotionMaterial::with(['episode.program', 'createdBy']);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $materials = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $materials,
                'message' => 'Promotion materials retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving promotion materials: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get statistics for Distribution Manager
     */
    public function statistics(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Distribution Manager') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $stats = [
                'total_schedules' => BroadcastingSchedule::count(),
                'pending_schedules' => BroadcastingSchedule::where('status', 'pending')->count(),
                'scheduled_schedules' => BroadcastingSchedule::where('status', 'scheduled')->count(),
                'published_schedules' => BroadcastingSchedule::where('status', 'published')->count(),
                'failed_schedules' => BroadcastingSchedule::where('status', 'failed')->count(),
                
                'schedules_by_platform' => BroadcastingSchedule::selectRaw('platform, count(*) as count')
                    ->groupBy('platform')
                    ->get(),
                
                'total_promotions' => PromotionMaterial::count(),
                'pending_promotions' => PromotionMaterial::where('status', 'pending')->count(),
                'approved_promotions' => PromotionMaterial::where('status', 'approved')->count(),
                
                'total_qc' => QualityControl::count(),
                'pending_qc' => QualityControl::where('status', 'pending')->count(),
                'approved_qc' => QualityControl::where('status', 'approved')->count(),
                
                'recent_schedules' => BroadcastingSchedule::with(['episode.program'])
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get(),
                
                'upcoming_schedules' => BroadcastingSchedule::with(['episode.program'])
                    ->where('schedule_date', '>=', now())
                    ->where('status', 'scheduled')
                    ->orderBy('schedule_date', 'asc')
                    ->limit(10)
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get dashboard overview
     */
    public function dashboard(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Distribution Manager') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $dashboard = [
                'today_schedules' => BroadcastingSchedule::whereDate('schedule_date', today())
                    ->with(['episode.program'])
                    ->get(),
                
                'pending_approvals' => BroadcastingSchedule::where('status', 'pending')
                    ->with(['episode.program'])
                    ->orderBy('schedule_date', 'asc')
                    ->get(),
                
                'active_programs' => Program::where('status', 'active')
                    ->withCount('episodes')
                    ->get(),
                
                'recent_activity' => BroadcastingSchedule::with(['episode.program', 'createdBy'])
                    ->orderBy('updated_at', 'desc')
                    ->limit(10)
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $dashboard,
                'message' => 'Dashboard data retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving dashboard: ' . $e->getMessage()
            ], 500);
        }
    }
}









