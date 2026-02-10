<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BroadcastingSchedule;
use App\Models\PromotionMaterial;
use App\Models\QualityControl;
use App\Models\Program;
use App\Models\Episode;
use App\Services\ProgramPerformanceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DistributionManagerController extends Controller
{
    protected $programPerformanceService;

    public function __construct(ProgramPerformanceService $programPerformanceService)
    {
        $this->programPerformanceService = $programPerformanceService;
    }
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

            $query = BroadcastingSchedule::with(['episode.program.productionTeam', 'createdBy']);

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
            $schedule = BroadcastingSchedule::with(['episode.program.productionTeam', 'createdBy'])
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

            $query = PromotionMaterial::with(['episode.program.productionTeam', 'createdBy']);

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

            $scheduleStats = BroadcastingSchedule::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $promotionStats = PromotionMaterial::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $qcStats = QualityControl::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $stats = [
            'total_schedules' => $scheduleStats->sum(),
            'pending_schedules' => $scheduleStats->get('pending', 0),
            'scheduled_schedules' => $scheduleStats->get('scheduled', 0),
            'published_schedules' => $scheduleStats->get('published', 0),
            'failed_schedules' => $scheduleStats->get('failed', 0),
            
            'schedules_by_platform' => BroadcastingSchedule::selectRaw('platform, count(*) as count')
                ->groupBy('platform')
                ->get(),
            
            'total_promotions' => $promotionStats->sum(),
            'pending_promotions' => $promotionStats->get('pending', 0),
            'approved_promotions' => $promotionStats->get('approved', 0),
            
            'total_qc' => $qcStats->sum(),
            'pending_qc' => $qcStats->get('pending', 0),
            'approved_qc' => $qcStats->get('approved', 0),
            
            'recent_schedules' => BroadcastingSchedule::with(['episode.program.productionTeam'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(),
                
                'upcoming_schedules' => BroadcastingSchedule::with(['episode.program.productionTeam'])
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
                    ->with(['episode.program.productionTeam'])
                    ->take(20) // Limit to relevant today
                    ->get(),
                
                'pending_approvals' => BroadcastingSchedule::where('status', 'pending')
                    ->with(['episode.program.productionTeam'])
                    ->orderBy('schedule_date', 'asc')
                    ->take(15) // Limit pending to most urgent
                    ->get(),
                
                'active_programs' => Program::where('status', 'active')
                    ->withCount('episodes')
                    ->take(10) // Only top active for dashboard
                    ->get(),
                
                'recent_activity' => BroadcastingSchedule::with(['episode.program.productionTeam', 'createdBy'])
                    ->orderBy('updated_at', 'desc')
                    ->limit(5) // Reduced limit for dashboard
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

            // Normalize input
            if (!$request->has('selected_option_index')) {
                if ($request->has('index')) $request->merge(['selected_option_index' => $request->index]);
                elseif ($request->has('option_index')) $request->merge(['selected_option_index' => $request->option_index]);
                elseif ($request->has('approved_option_id')) $request->merge(['selected_option_index' => $request->approved_option_id]);
            }

            // Normalize notes
            if (!$request->has('review_notes') && $request->has('approval_note')) {
                $request->merge(['review_notes' => $request->approval_note]);
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

            // Apply Schedule to Episodes
            try {
                $selectedSchedule = $scheduleOption->schedule_options[$selectedIndex];
                $newDate = \Carbon\Carbon::parse($selectedSchedule['date']); // Date component
                $newTime = $selectedSchedule['time']; // H:i component
                
                $applyTo = $scheduleOption->apply_to ?? 'all';
                $program = $scheduleOption->program;

                if ($applyTo === 'select' && !empty($scheduleOption->target_episode_ids)) {
                    // CASE: Apply to Specific Episodes
                    foreach ($scheduleOption->target_episode_ids as $epId) {
                        $episode = \App\Models\Episode::find($epId);
                        if ($episode) {
                            // Combine selected Date + Time
                            // NOTE: If user selects multiple episodes, they ALL get this Date+Time.
                            // This assumes 'select' is used for single episode revisions or batch move to same slot.
                            $newAirDate = \Carbon\Carbon::parse($selectedSchedule['date'] . ' ' . $newTime);
                            
                            $episode->update(['air_date' => $newAirDate]);
                            
                            // Update deadlines relative to new air date
                            if (method_exists($episode, 'updateDeadline')) {
                                $episode->updateDeadline('editor', $newAirDate->copy()->subDays(7), 'Reschedule Approved');
                                $episode->updateDeadline('kreatif', $newAirDate->copy()->subDays(9), 'Reschedule Approved');
                                $episode->updateDeadline('produksi', $newAirDate->copy()->subDays(9), 'Reschedule Approved');
                            }
                        }
                    }
                } else {
                    // CASE: Apply to All (Program Level)
                    // Update Program Start Date
                    $program->update([
                        'start_date' => $newDate->format('Y-m-d'),
                        'air_time' => $newTime
                    ]);
                    
                    // Re-calculate all 52 episodes relative to new Start Date
                    // We assume Episode 1 starts at Program Start Date
                    $episodes = $program->episodes()->orderBy('episode_number')->get();
                    
                    foreach ($episodes as $episode) {
                        // Calculate expected air date: Start Date + (EpNum - 1) Weeks
                        // Combined with new Time
                        $weeksToAdd = $episode->episode_number - 1;
                        $epAirDate = $newDate->copy()->addWeeks($weeksToAdd)->setTimeFromTimeString($newTime);
                        
                        $episode->update(['air_date' => $epAirDate]);
                        
                        // Update Deadlines
                        if (method_exists($episode, 'updateDeadline')) {
                             $episode->updateDeadline('editor', $epAirDate->copy()->subDays(7), 'Program Schedule Approved');
                             $episode->updateDeadline('kreatif', $epAirDate->copy()->subDays(9), 'Program Schedule Approved');
                             $episode->updateDeadline('produksi', $epAirDate->copy()->subDays(9), 'Program Schedule Approved');
                        }
                    }
                    
                    // If no episodes exist yet (first time approval), generate them?
                    // But generatedEpisodes() logic forces Saturday.
                    // So we rely on calling generateEpisodes() first, THEN update dates loop above?
                    // Or if episodes count == 0, we create them manually using this loop logic?
                    if ($episodes->count() === 0) {
                         // Fallback: Use existing generate logic (Sabtu) then update?
                         // Or better: Let ProgramProposalController handle initial generation (as verified before).
                         // Assuming episodes EXIST because ProgramProposalController generated them on Approval.
                         // So we just UPDATE them here. Correct.
                    }
                }

            } catch (\Exception $e) {
                \Log::error('Error applying schedule to episodes: ' . $e->getMessage());
                // Non-blocking error, continue to notify user
            }

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

            // Normalize input
            if (!$request->has('revision_notes')) {
                if ($request->has('revision_note')) $request->merge(['revision_notes' => $request->revision_note]);
                elseif ($request->has('note')) $request->merge(['revision_notes' => $request->note]);
                elseif ($request->has('notes')) $request->merge(['revision_notes' => $request->notes]);
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

            // Normalize input
            if (!$request->has('rejection_reason')) {
                if ($request->has('reason')) $request->merge(['rejection_reason' => $request->reason]);
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

    /**
     * Get weekly program performance (Target Pencapaian Views dll)
     * "Tarik data mingguan" sesuai requirement Distribution Manager
     */
    public function getProgramPerformance(int $programId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Distribution Manager') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Gunakan service yang sudah ada logic lengkapnya
            $report = $this->programPerformanceService->getWeeklyPerformanceReport($programId);

            return response()->json([
                'success' => true,
                'data' => $report,
                'message' => 'Weekly program performance retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving program performance: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Close non-performing program
     * "Menutup PRogram reguler yang tidak berkembang"
     */
    public function closeProgram(Request $request, int $programId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Distribution Manager') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. Only Distribution Manager can close programs.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $program = Program::findOrFail($programId);

            if ($program->status === 'cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => 'Program is already cancelled'
                ], 400);
            }

            // Close program
            $program->update([
                'status' => 'cancelled',
                'rejection_notes' => 'Closed by Distribution Manager: ' . $request->reason,
                'updated_at' => now()
            ]);

            // Notify Manager Program
            \App\Models\Notification::create([
                'user_id' => $program->manager_program_id,
                'type' => 'program_closed',
                'title' => 'Program Ditutup',
                'message' => "Program '{$program->name}' telah ditutup oleh Distribution Manager. Alasan: {$request->reason}",
                'data' => [
                    'program_id' => $program->id,
                    'reason' => $request->reason,
                    'closed_by' => $user->id
                ]
            ]);

            return response()->json([
                'success' => true,
                'data' => $program,
                'message' => 'Program closed successfully.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error closing program: ' . $e->getMessage()
            ], 500);
        }
    }
}