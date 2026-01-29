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
use Illuminate\Support\Facades\Validator;

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
                'message' => 'Error retrieving dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign work to distribution team members (berdasarkan jabatan)
     * Distribution Manager dapat membagi pekerjaan ke distribution team berdasarkan role
     */
    public function assignWork(Request $request, int $episodeId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Distribution Manager') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'role' => 'required|string|in:broadcasting,promotion,graphic_design,social_media,editor_promotion',
                'user_ids' => 'required|array|min:1',
                'user_ids.*' => 'required|exists:users,id',
                'work_type' => 'required|string|in:upload_youtube,upload_website,create_promotion,design_poster,social_media_post',
                'notes' => 'nullable|string|max:1000',
                'deadline' => 'nullable|date|after:now'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $episode = Episode::with('program')->findOrFail($episodeId);

            // Validate users have correct role
            $roleMapping = [
                'broadcasting' => 'Broadcasting',
                'promotion' => 'Promotion',
                'graphic_design' => 'Graphic Design',
                'social_media' => 'Social Media',
                'editor_promotion' => 'Editor Promotion'
            ];

            $requiredRole = $roleMapping[$request->role] ?? null;
            if (!$requiredRole) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid role specified'
                ], 400);
            }

            // Verify all users have the correct role
            $users = \App\Models\User::whereIn('id', $request->user_ids)
                ->where('role', $requiredRole)
                ->get();

            if ($users->count() !== count($request->user_ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some users do not have the required role: ' . $requiredRole
                ], 400);
            }

            // Create work assignments
            $assignments = [];
            $notifications = [];
            $now = now();

            foreach ($request->user_ids as $userId) {
                // Prepare notification
                $notifications[] = [
                    'user_id' => $userId,
                    'type' => 'work_assigned',
                    'title' => 'Pekerjaan Ditugaskan',
                    'message' => "Anda ditugaskan untuk {$request->work_type} untuk Episode {$episode->episode_number} - {$episode->title}",
                    'data' => json_encode([
                        'episode_id' => $episodeId,
                        'program_id' => $episode->program_id,
                        'work_type' => $request->work_type,
                        'role' => $request->role,
                        'assigned_by' => $user->id,
                        'deadline' => $request->deadline,
                        'notes' => $request->notes
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now
                ];

                $assignments[] = [
                    'user_id' => $userId,
                    'role' => $request->role,
                    'work_type' => $request->work_type
                ];
            }

            if (!empty($notifications)) {
                \App\Models\Notification::insert($notifications);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'episode_id' => $episodeId,
                    'episode_number' => $episode->episode_number,
                    'assignments' => $assignments,
                    'assigned_by' => $user->id,
                    'assigned_at' => now(),
                    'deadline' => $request->deadline,
                    'notes' => $request->notes
                ],
                'message' => 'Work assigned successfully to ' . count($assignments) . ' user(s)'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error assigning work',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available workers by role (untuk assign work)
     */
    public function getAvailableWorkers(string $role): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Distribution Manager') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $roleMapping = [
                'broadcasting' => 'Broadcasting',
                'promotion' => 'Promotion',
                'graphic_design' => 'Graphic Design',
                'social_media' => 'Social Media',
                'editor_promotion' => 'Editor Promotion'
            ];

            $userRole = $roleMapping[$role] ?? null;
            if (!$userRole) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid role. Allowed roles: ' . implode(', ', array_keys($roleMapping))
                ], 400);
            }

            $workers = \App\Models\User::where('role', $userRole)
                ->where('is_active', true)
                ->select('id', 'name', 'email', 'role')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $workers,
                'message' => 'Available workers retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving workers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all schedule options submitted by Manager Program (Program Musik)
     * Distribution Manager can view all pending/reviewed schedule options
     */
    public function getScheduleOptions(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Distribution Manager') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. Only Distribution Manager can view schedule options.'
                ], 403);
            }

            // ✅ Eager loading to avoid N+1
            $query = \App\Models\ProgramScheduleOption::with(['program', 'episode', 'submittedBy', 'reviewedBy'])
                ->orderBy('created_at', 'desc');

            // Filter by status if provided
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by program if provided
            if ($request->has('program_id')) {
                $query->where('program_id', $request->program_id);
            }

            $options = $query->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $options,
                'message' => 'Schedule options retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving schedule options: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve schedule option with selected schedule
     * Distribution Manager approves one of the submitted schedule options
     */
    public function approveScheduleOption(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Distribution Manager') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. Only Distribution Manager can approve schedules.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'selected_option_index' => 'required|integer|min:0',
                'review_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $scheduleOption = \App\Models\ProgramScheduleOption::with(['program', 'submittedBy'])->findOrFail($id);

            if ($scheduleOption->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending schedule options can be approved'
                ], 400);
            }

            // Validate selected index
            $selectedIndex = $request->selected_option_index;
            if (!isset($scheduleOption->schedule_options[$selectedIndex])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid option index selected'
                ], 400);
            }

            // Mark as approved
            $scheduleOption->markAsApproved($selectedIndex, $request->review_notes);

            // ✅ Notify Manager Program (who submitted)
            \App\Models\Notification::create([
                'user_id' => $scheduleOption->submitted_by,
                'type' => 'schedule_approved',
                'title' => 'Jadwal Tayang Disetujui',
                'message' => "Distribution Manager menyetujui jadwal tayang untuk program '{$scheduleOption->program->name}'. Jadwal terpilih: {$scheduleOption->approved_schedule['formatted']}",
                'data' => [
                    'schedule_option_id' => $scheduleOption->id,
                    'program_id' => $scheduleOption->program_id,
                    'episode_id' => $scheduleOption->episode_id,
                    'approved_schedule' => $scheduleOption->approved_schedule,
                    'review_notes' => $request->review_notes
                ]
            ]);

            return response()->json([
                'success' => true,
                'data' => $scheduleOption->fresh(['program', 'episode', 'submittedBy', 'reviewedBy']),
                'message' => 'Schedule approved successfully. Manager Program has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Revise schedule option (request changes)
     * Distribution Manager requests revision from Manager Program
     */
    public function reviseScheduleOption(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Distribution Manager') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. Only Distribution Manager can request revisions.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'revision_notes' => 'required|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $scheduleOption = \App\Models\ProgramScheduleOption::with(['program', 'submittedBy'])->findOrFail($id);

            if ($scheduleOption->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending schedule options can be revised'
                ], 400);
            }

            // Mark as revised
            $scheduleOption->markAsRevised($request->revision_notes);

            // ✅ Notify Manager Program
            \App\Models\Notification::create([
                'user_id' => $scheduleOption->submitted_by,
                'type' => 'schedule_revision_requested',
                'title' => 'Jadwal Tayang Perlu Revisi',
                'message' => "Distribution Manager meminta revisi jadwal tayang untuk program '{$scheduleOption->program->name}'. Catatan: {$request->revision_notes}",
                'data' => [
                    'schedule_option_id' => $scheduleOption->id,
                    'program_id' => $scheduleOption->program_id,
                    'episode_id' => $scheduleOption->episode_id,
                    'revision_notes' => $request->revision_notes
                ]
            ]);

            return response()->json([
                'success' => true,
                'data' => $scheduleOption->fresh(['program', 'episode', 'submittedBy', 'reviewedBy']),
                'message' => 'Revision requested successfully. Manager Program has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error requesting revision: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject schedule option
     * Distribution Manager rejects schedule options
     */
    public function rejectScheduleOption(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Distribution Manager') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. Only Distribution Manager can reject schedules.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'rejection_reason' => 'required|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $scheduleOption = \App\Models\ProgramScheduleOption::with(['program', 'submittedBy'])->findOrFail($id);

            if ($scheduleOption->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending schedule options can be rejected'
                ], 400);
            }

            // Mark as rejected
            $scheduleOption->markAsRejected($request->rejection_reason);

            // ✅ Notify Manager Program
            \App\Models\Notification::create([
                'user_id' => $scheduleOption->submitted_by,
                'type' => 'schedule_rejected',
                'title' => 'Jadwal Tayang Ditolak',
                'message' => "Distribution Manager menolak jadwal tayang untuk program '{$scheduleOption->program->name}'. Alasan: {$request->rejection_reason}",
                'data' => [
                    'schedule_option_id' => $scheduleOption->id,
                    'program_id' => $scheduleOption->program_id,
                    'episode_id' => $scheduleOption->episode_id,
                    'rejection_reason' => $request->rejection_reason
                ]
            ]);

            return response()->json([
                'success' => true,
                'data' => $scheduleOption->fresh(['program', 'episode', 'submittedBy', 'reviewedBy']),
                'message' => 'Schedule rejected successfully. Manager Program has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting schedule: ' . $e->getMessage()
            ], 500);
        }
    }
}