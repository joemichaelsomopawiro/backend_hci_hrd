<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BroadcastingSchedule;
use App\Models\BroadcastingWork;
use App\Models\EditorWork;
use App\Models\Episode;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ManagerBroadcastingController extends Controller
{
    /**
     * Get all broadcasting schedules for approval
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }
            
            if ($user->role !== 'Distribution Manager') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $query = BroadcastingSchedule::with([
            'episode.program.managerProgram',
            'episode.program.productionTeam.members.user',
            'createdBy',
            'uploadedBy'
        ]);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by platform
            if ($request->has('platform')) {
                $query->where('platform', $request->platform);
            }

            $schedules = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $schedules,
                'message' => 'Broadcasting schedules retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving broadcasting schedules: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get broadcasting works for approval
     */
    public function getBroadcastingWorks(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }
            
            $allowedRoles = ['Distribution Manager', 'Manager Broadcasting'];
            if (!in_array($user->role, $allowedRoles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $query = BroadcastingWork::with([
            'episode.program.managerProgram',
            'episode.program.productionTeam.members.user',
            'createdBy',
            'editorWork.createdBy'
        ]);

            // Filter by status
            if ($request->has('status')) {
                $status = $request->status;
                if (is_string($status) && str_contains($status, ',')) {
                    $status = explode(',', $status);
                }
                
                if (is_array($status)) {
                    $query->whereIn('status', $status);
                } else {
                    $query->where('status', $status);
                }
            }

            $works = $query->orderBy('created_at', 'desc')->paginate(15);

            // Untuk History tab: work dengan status 'pending' + approved_at = sudah di-approve QC → tampilkan sebagai 'approved'
            $items = $works->getCollection()->map(function ($work) {
                $item = $work->toArray();
                if ($work->status === 'pending' && $work->approved_at) {
                    $item['display_status'] = 'approved';
                } elseif ($work->status === 'rejected') {
                    $item['display_status'] = 'rejected';
                } else {
                    $item['display_status'] = $work->status;
                }
                return $item;
            });
            $works->setCollection($items);

            return response()->json([
                'success' => true,
                'data' => $works,
                'message' => 'Broadcasting works retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving broadcasting works: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve broadcasting schedule
     */
    public function approveSchedule(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }
            
            if ($user->role !== 'Distribution Manager') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'approval_notes' => 'nullable|string',
                'scheduled_time' => 'nullable|date|after:now',
                'priority' => 'nullable|in:low,medium,high,urgent'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $schedule = BroadcastingSchedule::findOrFail($id);

            if ($schedule->status !== 'pending_approval') {
                return response()->json([
                    'success' => false,
                    'message' => 'Broadcasting schedule is not pending approval'
                ], 400);
            }

            $schedule->update([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'approval_notes' => $request->approval_notes,
                'scheduled_time' => $request->scheduled_time ?? $schedule->scheduled_time,
                'priority' => $request->priority ?? 'medium'
            ]);

            // Notify Broadcasting team
            $this->notifyBroadcastingTeam($schedule, 'approved');

            return response()->json([
                'success' => true,
                'data' => $schedule->load(['episode', 'createdBy', 'approvedBy']),
                'message' => 'Broadcasting schedule approved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving broadcasting schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject broadcasting schedule
     */
    public function rejectSchedule(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }
            
            if ($user->role !== 'Distribution Manager') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'rejection_reason' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $schedule = BroadcastingSchedule::findOrFail($id);

            if ($schedule->status !== 'pending_approval') {
                return response()->json([
                    'success' => false,
                    'message' => 'Broadcasting schedule is not pending approval'
                ], 400);
            }

            $schedule->update([
                'status' => 'rejected',
                'rejected_by' => $user->id,
                'rejected_at' => now(),
                'rejection_reason' => $request->rejection_reason
            ]);

            // Notify Broadcasting team
            $this->notifyBroadcastingTeam($schedule, 'rejected');

            return response()->json([
                'success' => true,
                'data' => $schedule->load(['episode', 'createdBy', 'rejectedBy']),
                'message' => 'Broadcasting schedule rejected successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting broadcasting schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve broadcasting work
     */
    public function approveWork(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }

            $allowedRoles = ['Distribution Manager', 'Manager Broadcasting'];
            if (!in_array($user->role, $allowedRoles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Normalisasi input notes
            if (!$request->has('approval_notes')) {
                if ($request->has('notes')) $request->merge(['approval_notes' => $request->notes]);
                elseif ($request->has('note')) $request->merge(['approval_notes' => $request->note]);
            }

            $validator = Validator::make($request->all(), [
                'approval_notes' => 'nullable|string',
                'publish_time' => 'nullable|date|after:now'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = BroadcastingWork::findOrFail($id);

            if ($work->status !== 'pending_approval') {
                return response()->json([
                    'success' => false,
                    'message' => 'Broadcasting work is not pending approval'
                ], 400);
            }

            $work->update([
                'status' => 'pending', // Set to pending so Broadcasting team can see it
                'approved_by' => $user->id,
                'approved_at' => now(),
                'approval_notes' => $request->approval_notes,
                'scheduled_time' => $request->publish_time ?? $work->scheduled_time
            ]);

            // ✨ Sync with EditorWork: Set terminal status to 'approved' ✨
            $editorWork = null;
            if ($work->editor_work_id) {
                $editorWork = EditorWork::find($work->editor_work_id);
            }
            if (!$editorWork) {
                $editorWork = EditorWork::where('episode_id', $work->episode_id)
                    ->where('work_type', $work->work_type)
                    ->where('status', 'submitted')
                    ->latest()
                    ->first();
            }

            if ($editorWork) {
                $editorWork->update([
                    'status' => 'approved',
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                    'review_notes' => $request->approval_notes
                ]);
            }

            $episode = $work->episode()->with('program.productionTeam')->first();
            $catatanQc = $request->approval_notes ?: null;

            // Notify Producer (QC approved – bisa baca hasil QC)
            $producerId = $episode?->program?->productionTeam?->producer_id;
            if ($producerId) {
                Notification::create([
                    'user_id' => $producerId,
                    'type' => 'qc_editor_work_approved',
                    'title' => 'QC Program: Hasil Editor Disetujui',
                    'message' => "QC (Distribution Manager) telah menyetujui hasil editor Episode #{$work->episode?->episode_number}. " . ($catatanQc ? "Catatan QC: {$catatanQc}" : ''),
                    'episode_id' => $work->episode_id,
                    'data' => [
                        'broadcasting_work_id' => $work->id,
                        'editor_work_id' => $editorWork?->id,
                        'catatan_qc' => $catatanQc,
                        'approved_by' => $user->name ?? 'QC',
                    ],
                ]);
            }

            // Notify Produksi (role Produksi / Production) – Terima Notifikasi QC, Baca Hasil QC
            $produksiUsers = User::whereIn('role', ['Produksi', 'Production'])->pluck('id');
            foreach ($produksiUsers as $produksiUserId) {
                if ($produksiUserId == $producerId) continue; // avoid duplicate
                Notification::create([
                    'user_id' => $produksiUserId,
                    'type' => 'qc_result_ready',
                    'title' => 'Hasil QC Siap Dibaca',
                    'message' => "Hasil QC dari Distribution Manager untuk Episode #{$work->episode?->episode_number} telah tersedia. " . ($catatanQc ? "Catatan QC: {$catatanQc}" : ''),
                    'episode_id' => $work->episode_id,
                    'data' => [
                        'broadcasting_work_id' => $work->id,
                        'catatan_qc' => $catatanQc,
                    ],
                ]);
            }

            // Notify Broadcasting team (Terima Notifikasi, Terima File materi dari QC)
            $this->notifyBroadcastingTeam($work, 'work_approved', $catatanQc);

            return response()->json([
                'success' => true,
                'data' => $work->load(['episode', 'createdBy', 'approvedBy', 'editorWork.createdBy']),
                'message' => 'Broadcasting work approved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving broadcasting work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject broadcasting work
     */
    public function rejectWork(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }

            $allowedRoles = ['Distribution Manager', 'Manager Broadcasting'];
            if (!in_array($user->role, $allowedRoles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Normalisasi input rejection notes
            if (!$request->has('rejection_notes')) {
                if ($request->has('revision_notes')) $request->merge(['rejection_notes' => $request->revision_notes]);
                elseif ($request->has('revision_note')) $request->merge(['rejection_notes' => $request->revision_note]);
                elseif ($request->has('notes')) $request->merge(['rejection_notes' => $request->notes]);
                elseif ($request->has('note')) $request->merge(['rejection_notes' => $request->note]);
            }

            $validator = Validator::make($request->all(), [
                'rejection_notes' => 'required|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = BroadcastingWork::findOrFail($id);

            if ($work->status !== 'pending_approval') {
                return response()->json([
                    'success' => false,
                    'message' => 'Broadcasting work is not pending approval'
                ], 400);
            }

            $work->update([
                'status' => 'rejected',
                'rejected_by' => $user->id,
                'rejected_at' => now(),
                'rejection_notes' => $request->rejection_notes,
            ]);

            // ✨ Reset EditorWork status and notify Editor/Producer ✨
            $editorWork = null;
            
            // 1. Precise find by editor_work_id
            if ($work->editor_work_id) {
                $editorWork = EditorWork::find($work->editor_work_id);
            }
            
            // 2. Fallback to existing logic if link is missing
            if (!$editorWork) {
                $editorWork = EditorWork::where('episode_id', $work->episode_id)
                    ->where('work_type', $work->work_type)
                    ->whereIn('status', ['completed', 'submitted']) // Must be one that was submitted
                    ->latest()
                    ->first();
            }

            // 3. Final fallback: search only by episode and status if work_type also mismatch
            if (!$editorWork) {
                $editorWork = EditorWork::where('episode_id', $work->episode_id)
                    ->whereIn('status', ['completed', 'submitted'])
                    ->latest()
                    ->first();
            }

            if ($editorWork) {
                $editorWork->update([
                    'status' => 'rejected',
                    'qc_feedback' => "[QC REJECTED - " . now()->format('Y-m-d H:i:s') . "]\n" . $request->rejection_notes
                ]);

                // Notify Editor – Kembali ke Editor untuk revisi
                Notification::create([
                    'user_id' => $editorWork->created_by,
                    'type' => 'work_rejected',
                    'title' => 'QC REJECTED: Kembali ke Editor',
                    'message' => "Hasil editing episode #{$work->episode?->episode_number} ditolak oleh QC (Distribution Manager). Pekerjaan dikembalikan ke Editor untuk revisi. Catatan: {$request->rejection_notes}",
                    'episode_id' => $work->episode_id,
                    'data' => [
                        'episode_id' => $work->episode_id,
                        'notes' => $request->rejection_notes,
                        'editor_work_id' => $editorWork->id,
                        'kembali_ke_editor' => true,
                    ]
                ]);
            }

            // Notify Producer (Manager Program) AND Team Producer
            $episode = $work->episode()->with('program.productionTeam')->first();
            $managerProgramId = $episode?->program?->manager_program_id;
            $teamProducerId = $episode?->program?->productionTeam?->producer_id;

            // Recipient IDs (unique)
            $recipientIds = array_unique(array_filter([$managerProgramId, $teamProducerId]));

            foreach ($recipientIds as $recipientId) {
                Notification::create([
                    'user_id' => $recipientId,
                    'type' => 'qc_alert',
                    'title' => 'QC Alert: Kembali ke Editor',
                    'message' => "QC (Distribution Manager) menolak hasil editing episode #{$work->episode?->episode_number}. Pekerjaan dikembalikan ke Editor untuk revisi. Catatan: {$request->rejection_notes}",
                    'episode_id' => $work->episode_id,
                    'data' => [
                        'episode_id' => $work->episode_id,
                        'editor_name' => $editorWork?->createdBy?->name,
                        'rejection_notes' => $request->rejection_notes,
                        'kembali_ke_editor' => true,
                    ]
                ]);
            }

            // Notify Broadcasting team
            $this->notifyBroadcastingTeam($work, 'rejected');

            return response()->json([
                'success' => true,
                'data' => $work->load(['episode', 'createdBy', 'rejectedBy', 'editorWork.createdBy']),
                'message' => 'Broadcasting work rejected successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting broadcasting work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get schedule options dari Manager Program
     */
    public function getScheduleOptions(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }
            
            if ($user->role !== 'Distribution Manager') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $query = \App\Models\ProgramScheduleOption::with(['program', 'episode', 'submittedBy'])
                ->where('status', 'pending');

            // Filter by program
            if ($request->has('program_id')) {
                $query->where('program_id', $request->program_id);
            }

            $options = $query->orderBy('created_at', 'desc')->paginate(15);

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
     * Approve/Select schedule option dari Manager Program
     */
    public function approveScheduleOption(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }
            
            if ($user->role !== 'Distribution Manager') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
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

            $scheduleOption = \App\Models\ProgramScheduleOption::findOrFail($id);

            if ($scheduleOption->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Schedule option is not pending approval'
                ], 400);
            }

            // Validate option index
            if (!isset($scheduleOption->schedule_options[$request->selected_option_index])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid option index'
                ], 400);
            }

            $selectedOption = $scheduleOption->schedule_options[$request->selected_option_index];

            // Update schedule option
            $scheduleOption->update([
                'status' => 'approved',
                'selected_option_index' => $request->selected_option_index,
                'selected_schedule_date' => $selectedOption['datetime'],
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'review_notes' => $request->review_notes
            ]);

            // Create or update BroadcastingSchedule jika ada episode_id
            if ($scheduleOption->episode_id) {
                $broadcastingSchedule = BroadcastingSchedule::updateOrCreate(
                    [
                        'episode_id' => $scheduleOption->episode_id,
                        'platform' => $scheduleOption->platform === 'all' ? 'tv' : $scheduleOption->platform
                    ],
                    [
                        'schedule_date' => $selectedOption['datetime'],
                        'status' => 'approved',
                        'created_by' => $user->id,
                        'title' => $scheduleOption->episode->title ?? "Episode {$scheduleOption->episode->episode_number}",
                        'description' => $request->review_notes ?? "Jadwal dipilih dari opsi Manager Program"
                    ]
                );
            } else {
                // AUTO-GENERATE EPISODES if Program Schedule (not specific episode)
                // Logic: If schedule approved for Program, and no episodes exist, generate 52 episodes for that year.
                $program = $scheduleOption->program;
                if ($program) {
                     $selectedDate = Carbon::parse($selectedOption['datetime']);
                     $selectedYear = $selectedDate->year;
                     
                     // Check existing episodes for this year
                     $yearStart = Carbon::createFromDate($selectedYear, 1, 1, 'UTC')->setTime(0, 0, 0);
                    $yearEnd = Carbon::createFromDate($selectedYear, 12, 31, 'UTC')->setTime(23, 59, 59);
                     
                     $existingCount = Episode::where('program_id', $program->id)
                        ->whereBetween('air_date', [$yearStart, $yearEnd])
                        ->whereNull('deleted_at')
                        ->count();
                        
                    if ($existingCount === 0) {
                        // Generate 52 weeks starting from FIRST SATURDAY of the year (music program requirement)
                        // Ignore hari di schedule option; selalu pakai Sabtu pertama di tahun tersebut
                        $genResult = $program->generateEpisodesForYear($selectedYear, Carbon::SATURDAY);
                         
                         if ($genResult['success']) {
                             // Log or Notify
                             Notification::create([
                                'title' => 'Episodes Auto-Generated',
                                'message' => "Automatic 52 episodes generated for Program '{$program->name}' upon schedule approval.",
                                'type' => 'system_notification',
                                'user_id' => $user->id, // Distribution Manager gets info
                                'data' => ['year' => $selectedYear, 'count' => 52]
                             ]);
                             // Notify Program Manager too
                             if ($program->manager_program_id) {
                                  Notification::create([
                                    'title' => 'Episodes Auto-Generated',
                                    'message' => "Jadwal disetujui, sistem otomatis membuat 52 episode untuk tahun {$selectedYear}.",
                                    'type' => 'system_notification',
                                    'user_id' => $program->manager_program_id,
                                    'data' => ['year' => $selectedYear, 'count' => 52]
                                 ]);
                             }
                         }
                     }
                }
            }

            // Notify Manager Program
            $this->notifyManagerProgramScheduleApproved($scheduleOption);

            return response()->json([
                'success' => true,
                'data' => [
                    'schedule_option' => $scheduleOption->load(['program', 'episode', 'submittedBy', 'reviewedBy']),
                    'selected_option' => $selectedOption,
                    'broadcasting_schedule' => $broadcastingSchedule ?? null
                ],
                'message' => 'Schedule option approved successfully. Manager Program has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving schedule option: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject schedule option dari Manager Program
     */
    public function rejectScheduleOption(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }
            
            if ($user->role !== 'Distribution Manager') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
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

            $scheduleOption = \App\Models\ProgramScheduleOption::findOrFail($id);

            if ($scheduleOption->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Schedule option is not pending approval'
                ], 400);
            }

            // Update schedule option
            $scheduleOption->update([
                'status' => 'rejected',
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'rejection_reason' => $request->rejection_reason
            ]);

            // Notify Manager Program
            $this->notifyManagerProgramScheduleRejected($scheduleOption, $request->rejection_reason);

            return response()->json([
                'success' => true,
                'data' => $scheduleOption->load(['program', 'episode', 'submittedBy', 'reviewedBy']),
                'message' => 'Schedule option rejected successfully. Manager Program has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting schedule option: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notify Manager Program about approved schedule option
     */
    private function notifyManagerProgramScheduleApproved($scheduleOption): void
    {
        $program = $scheduleOption->program;
        
        if ($program && $program->managerProgram) {
            $selectedOption = $scheduleOption->getSelectedOptionAttribute();
            
            Notification::create([
                'title' => 'Opsi Jadwal Diterima',
                'message' => "Opsi jadwal tayang untuk program '{$program->name}' telah diterima. Jadwal yang dipilih: {$selectedOption['formatted']}",
                'type' => 'schedule_option_approved',
                'user_id' => $program->manager_program_id,
                'data' => [
                    'schedule_option_id' => $scheduleOption->id,
                    'program_id' => $program->id,
                    'selected_schedule' => $selectedOption
                ]
            ]);
        }
    }

    /**
     * Notify Manager Program about rejected schedule option
     */
    private function notifyManagerProgramScheduleRejected($scheduleOption, $reason): void
    {
        $program = $scheduleOption->program;
        
        if ($program && $program->managerProgram) {
            Notification::create([
                'title' => 'Opsi Jadwal Ditolak',
                'message' => "Opsi jadwal tayang untuk program '{$program->name}' telah ditolak. Alasan: {$reason}",
                'type' => 'schedule_option_rejected',
                'user_id' => $program->manager_program_id,
                'data' => [
                    'schedule_option_id' => $scheduleOption->id,
                    'program_id' => $program->id,
                    'rejection_reason' => $reason
                ]
            ]);
        }
    }

    /**
     * Get broadcasting statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }
            
            if ($user->role !== 'Distribution Manager') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $stats = [
                'total_schedules' => BroadcastingSchedule::count(),
                'pending_schedules' => BroadcastingSchedule::where('status', 'pending_approval')->count(),
                'approved_schedules' => BroadcastingSchedule::where('status', 'approved')->count(),
                'rejected_schedules' => BroadcastingSchedule::where('status', 'rejected')->count(),
                'total_works' => BroadcastingWork::count(),
                'pending_works' => BroadcastingWork::where('status', 'pending_approval')->count(),
                'approved_works' => BroadcastingWork::where('status', 'approved')->count(),
                'published_works' => BroadcastingWork::where('status', 'published')->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Broadcasting statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Revise broadcasting schedule
     * User: "Merefiss jadwal yang dan memberitahukan kembali ke manager program jadwal tayang yg di ACC"
     */
    public function reviseSchedule(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }
            
            if ($user->role !== 'Distribution Manager') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'new_schedule_date' => 'required|date|after:now',
                'reason' => 'required|string|max:1000',
                'notes' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $schedule = BroadcastingSchedule::findOrFail($id);

            if (!in_array($schedule->status, ['approved', 'scheduled'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only approved or scheduled schedules can be revised'
                ], 400);
            }

            // Save old schedule for history
            $oldScheduleDate = $schedule->schedule_date ?? $schedule->scheduled_time ?? null;

            // Update schedule
            $schedule->update([
                'schedule_date' => $request->new_schedule_date,
                'upload_notes' => ($schedule->upload_notes ? $schedule->upload_notes . "\n\n" : '') . 
                          "REVISED: {$request->reason}" . 
                          ($request->notes ? "\n{$request->notes}" : '')
            ]);

            // Notify Broadcasting team
            $this->notifyBroadcastingTeam($schedule, 'revised');

            // Notify Manager Program
            $this->notifyManagerProgram($schedule, $oldScheduleDate, $request->new_schedule_date, $request->reason);

            return response()->json([
                'success' => true,
                'data' => [
                    'schedule' => $schedule->load(['episode', 'createdBy']),
                    'old_schedule_date' => $oldScheduleDate,
                    'new_schedule_date' => $request->new_schedule_date,
                    'reason' => $request->reason
                ],
                'message' => 'Broadcasting schedule revised successfully. Manager Program has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error revising schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notify Manager Program about schedule revision
     */
    private function notifyManagerProgram($schedule, $oldDate, $newDate, $reason): void
    {
        $episode = $schedule->episode;
        $program = $episode->program ?? null;

        if ($program && $program->managerProgram) {
            Notification::create([
                'title' => 'Jadwal Tayang Diubah',
                'message' => "Jadwal tayang untuk program '{$program->name}' - Episode {$episode->episode_number} telah diubah dari " . 
                           Carbon::parse($oldDate)->format('d M Y H:i') . " menjadi " . 
                           Carbon::parse($newDate)->format('d M Y H:i') . ". Alasan: {$reason}",
                'type' => 'broadcasting_schedule_revised',
                'user_id' => $program->manager_program_id,
                'episode_id' => $episode->id,
                'data' => [
                    'schedule_id' => $schedule->id,
                    'program_id' => $program->id,
                    'old_schedule_date' => $oldDate,
                    'new_schedule_date' => $newDate,
                    'reason' => $reason
                ]
            ]);
        }

        // Also notify all Manager Program users
        $managerProgramUsers = \App\Models\User::where('role', 'Manager Program')->get();
        
        foreach ($managerProgramUsers as $managerUser) {
            if ($program && $program->manager_program_id === $managerUser->id) {
                continue; // Already notified above
            }

            Notification::create([
                'title' => 'Jadwal Tayang Diubah',
                'message' => "Jadwal tayang untuk episode {$episode->episode_number} telah diubah. Alasan: {$reason}",
                'type' => 'broadcasting_schedule_revised',
                'user_id' => $managerUser->id,
                'episode_id' => $episode->id,
                'data' => [
                    'schedule_id' => $schedule->id,
                    'old_schedule_date' => $oldDate,
                    'new_schedule_date' => $newDate,
                    'reason' => $reason
                ]
            ]);
        }
    }

    /**
     * Notify broadcasting team (Terima Notifikasi, Terima File materi dari QC / Distribution Manager)
     * @param object $item BroadcastingWork|BroadcastingSchedule
     * @param string $action approved|rejected|work_approved|revised
     * @param string|null $catatanQc Catatan QC untuk work_approved
     */
    private function notifyBroadcastingTeam($item, string $action, ?string $catatanQc = null): void
    {
        $epNum = $item->episode ? $item->episode->episode_number : $item->episode_id;
        $messages = [
            'approved' => "Jadwal broadcasting untuk episode #{$epNum} telah disetujui.",
            'rejected' => "Jadwal broadcasting untuk episode #{$epNum} telah ditolak.",
            'work_approved' => "Materi dari QC (Distribution Manager) untuk Episode #{$epNum} telah disetujui. Terima pekerjaan dan proses: Jadwal Playlist, Upload YouTube, Upload Website, input link YT, selesaikan pekerjaan." . ($catatanQc ? " Catatan QC: {$catatanQc}" : ''),
            'revised' => "Jadwal broadcasting untuk episode #{$epNum} telah direvisi."
        ];

        $broadcastingUsers = User::where('role', 'Broadcasting')->get();
        $message = $messages[$action] ?? "Broadcasting {$action}";

        foreach ($broadcastingUsers as $user) {
            $data = ['episode_id' => $item->episode_id];
            if ($action === 'work_approved') {
                $data['broadcasting_work_id'] = $item->id;
                $data['catatan_qc'] = $catatanQc;
                $data['file_from_qc'] = true;
            }

            Notification::create([
                'title' => $action === 'work_approved' ? 'Terima File Materi dari QC (Distribution Manager)' : 'Broadcasting ' . ucfirst(str_replace('_', ' ', $action)),
                'message' => $message,
                'type' => 'broadcasting_' . $action,
                'user_id' => $user->id,
                'episode_id' => $item->episode_id,
                'data' => $data,
            ]);
        }
    }
}
