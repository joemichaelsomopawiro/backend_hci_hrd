<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MusicArrangement;
use App\Models\Episode;
use App\Models\Notification;
use App\Services\WorkAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class MusicArrangerController extends Controller
{
    /**
     * Get Music Arrangements for current user
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
            
            // IZINKAN AKSES: Music Arranger, Creative, dan Producer
            $userRole = strtolower($user->role);
            $allowedRoles = ['music arranger', 'creative', 'producer'];
            
            if (!in_array($userRole, $allowedRoles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. Your role: ' . $user->role
                ], 403);
            }

            // Include soundEngineerHelper relationship untuk menampilkan info Sound Engineer jika ada
            $query = MusicArrangement::with(['episode', 'createdBy', 'reviewedBy', 'soundEngineerHelper']);

            // Music Arranger hanya bisa melihat miliknya sendiri
            // Creative dan Producer bisa melihat semua aransemen yang relevan
            if ($userRole === 'music arranger') {
                $query->where('created_by', $user->id);
            }

            // MAPPING STATUS: Frontend 'approved' -> Backend 'arrangement_approved'
            // Pastikan semua arrangement yang di-approve muncul, baik yang langsung dari Music Arranger
            // maupun yang sudah dibantu Sound Engineer
            if ($request->has('status')) {
                $statuses = explode(',', $request->status);
                $mappedStatuses = [];
                
                foreach ($statuses as $status) {
                    $s = trim($status);
                    $mappedStatuses[] = $s;
                    if ($s === 'approved') {
                        // Include both 'approved' and 'arrangement_approved' status
                        $mappedStatuses[] = 'arrangement_approved';
                    }
                    if ($s === 'rejected') {
                        $mappedStatuses[] = 'arrangement_rejected';
                    }
                    if ($s === 'submitted') {
                        $mappedStatuses[] = 'arrangement_submitted';
                    }
                }
                
                $query->whereIn('status', array_unique($mappedStatuses));
            }

            // Filter berdasarkan Episode
            if ($request->has('episode_id')) {
                $query->where('episode_id', $request->episode_id);
            }

            // Filter untuk "Terima Pekerjaan"
            if ($request->has('ready_for_arrangement') && $request->ready_for_arrangement == 'true') {
                $query->whereIn('status', ['song_approved', 'song_rejected']);
            }

            $arrangements = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $arrangements
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving arrangements: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new Music Arrangement
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }
            
            if ($user->role !== 'Music Arranger') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // NOTE: file_link tidak diterima saat create
            // Flow yang benar: create song proposal -> Producer approve -> upload file_link
            $validator = Validator::make($request->all(), [
                'episode_id' => 'required|exists:episodes,id',
                'song_id' => 'nullable|exists:songs,id', 
                'song_title' => 'required_without:song_id|string|max:255', 
                'singer_id' => 'nullable|exists:users,id', 
                'singer_name' => 'nullable|string|max:255',
                'arrangement_notes' => 'nullable|string',
                // file_link REMOVED - harus melalui workflow song proposal dulu
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $episode = Episode::with(['productionTeam.members', 'program.productionTeam.members'])->findOrFail($request->episode_id);
            $productionTeam = $episode->productionTeam ?? ($episode->program ? $episode->program->productionTeam : null);
            
            if (!$productionTeam) {
                return response()->json([
                    'success' => false,
                    'message' => 'Episode tidak memiliki ProductionTeam yang di-assign'
                ], 403);
            }

            $isMember = $productionTeam->members()
                ->where('user_id', $user->id)
                ->where('role', 'musik_arr')
                ->where('is_active', true)
                ->exists();

            if (!$isMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak di-assign sebagai Music Arranger di ProductionTeam episode ini.'
                ], 403);
            }

            $songTitle = $request->song_title;
            $songId = $request->song_id;
            
            // Auto-save Song to master data
            if ($songTitle && (!$songId || $request->has('song_title'))) {
                $song = \App\Models\Song::firstOrCreate(
                    ['title' => $songTitle],
                    ['status' => 'available', 'created_by' => $user->id]
                );
                $songId = $song->id;
                $songTitle = $song->title;
            } elseif ($songId && !$songTitle) {
                $song = \App\Models\Song::find($songId);
                if ($song) $songTitle = $song->title;
            }

            $singerName = $request->singer_name;
            $singerId = $request->singer_id;
            
            // Auto-save Singer to master data
            if ($singerName && (!$singerId || $request->has('singer_name'))) {
                $singer = \App\Models\Singer::firstOrCreate(
                    ['name' => $singerName],
                    ['is_active' => true]
                );
                $singerId = $singer->id;
                $singerName = $singer->name;
            } elseif ($singerId && !$singerName) {
                $singer = \App\Models\Singer::find($singerId);
                if ($singer) $singerName = $singer->name;
            }


            // file_link tidak boleh diisi saat create - harus melalui workflow
            // Status selalu dimulai dari song_proposal
            $status = 'song_proposal';

            // AUTO-ASSIGNMENT LOGIC: Use WorkAssignmentService to determine assignee
            // Checks if previous episode's MusicArrangement was reassigned
            $assignedUserId = WorkAssignmentService::getNextAssignee(
                MusicArrangement::class,
                $episode->program_id,
                $episode->episode_number,
                null,  // MusicArrangement doesn't have work_type
                $user->id
            );

            $arrangement = MusicArrangement::create([
                'episode_id' => $request->episode_id,
                'song_id' => $songId,
                'singer_id' => $singerId,
                'song_title' => $songTitle,
                'singer_name' => $singerName,
                'original_song_title' => $songTitle,
                'original_singer_name' => $singerName,
                'arrangement_notes' => $request->arrangement_notes,
                'file_link' => null,  // Tidak bisa diisi saat create, harus melalui uploadFile setelah song_approved
                // Old fields kept as null for backward compatibility
                'file_path' => null,
                'file_name' => null,
                'file_size' => null,
                'mime_type' => null,
                'status' => $status,
                'created_by' => $assignedUserId,          // AUTO-ASSIGNED
                'originally_assigned_to' => null,          // Reset
                'was_reassigned' => false                  // Reset
            ]);

            $producer = $productionTeam->producer;
            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => $status === 'song_proposal' ? 'song_proposal_submitted' : 'music_arrangement_created',
                    'title' => $status === 'song_proposal' ? 'Usulan Lagu Baru' : 'Arrangement Baru',
                    'message' => "Music Arranger {$user->name} mengirim " . ($status === 'song_proposal' ? "usulan lagu" : "file arrangement") . " untuk Episode {$episode->episode_number}.",
                    'data' => ['arrangement_id' => $arrangement->id, 'episode_id' => $arrangement->episode_id]
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Music arrangement created successfully',
                'data' => $arrangement->load(['episode', 'createdBy', 'song', 'singer'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get specific Music Arrangement
     */
    public function show($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $arrangement = MusicArrangement::with([
                'episode', 
                'createdBy', 
                'reviewedBy', 
                'soundEngineerHelper'
            ])->findOrFail($id);

            // Access check: creator or team members
            return response()->json(['success' => true, 'data' => $arrangement]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update Music Arrangement / Song Proposal
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $arrangement = MusicArrangement::findOrFail($id);

            // Validate Music Arranger is the creator
            if ($arrangement->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You can only update your own arrangements.'
                ], 403);
            }

            // Allow modification if status is draft, song_proposal, or song_rejected
            $allowedStatuses = ['draft', 'song_proposal', 'song_rejected', 'arrangement_in_progress', 'arrangement_rejected'];
            if (!in_array($arrangement->status, $allowedStatuses)) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot update arrangement with status '{$arrangement->status}'."
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'song_id' => 'nullable|exists:songs,id',
                'song_title' => 'nullable|string|max:255',
                'singer_id' => 'nullable|exists:singers,id',
                'singer_name' => 'nullable|string|max:255',
                'arrangement_notes' => 'nullable|string',
                'file_link' => 'nullable|url|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $songTitle = $request->song_title ?? $arrangement->song_title;
            $songId = $request->song_id ?? $arrangement->song_id;
            
            // Auto-save Song to master data
            if ($songTitle && ($request->has('song_title') || !$songId)) {
                $song = \App\Models\Song::firstOrCreate(
                    ['title' => $songTitle],
                    ['status' => 'available', 'created_by' => $user->id]
                );
                $songId = $song->id;
                $songTitle = $song->title;
            }

            $singerName = $request->singer_name ?? $arrangement->singer_name;
            $singerId = $request->singer_id ?? $arrangement->singer_id;

            // Auto-save Singer to master data
            if ($singerName && ($request->has('singer_name') || !$singerId)) {
                $singer = \App\Models\Singer::firstOrCreate(
                    ['name' => $singerName],
                    ['is_active' => true]
                );
                $singerId = $singer->id;
                $singerName = $singer->name;
            }

            $updateData = [
                'song_id' => $songId,
                'song_title' => $songTitle,
                'singer_id' => $singerId,
                'singer_name' => $singerName,
                'arrangement_notes' => $request->arrangement_notes ?? $arrangement->arrangement_notes,
                'file_link' => $request->file_link ?? $arrangement->file_link,
            ];

            // If it was rejected, reset to appropriate submission status
            if ($arrangement->status === 'song_rejected') {
                $updateData['status'] = 'song_proposal';
                $updateData['submitted_at'] = now();
            }

            $arrangement->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Arrangement updated successfully.',
                'data' => $arrangement->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating arrangement: ' . $e->getMessage()
            ], 500);
        }
    }

    public function uploadFile(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $arrangement = MusicArrangement::findOrFail($id);

            // Validate Music Arranger is the creator
            if ($arrangement->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You can only update your own arrangements.'
                ], 403);
            }

            // Validate file_link
            $validator = Validator::make($request->all(), [
                'file_link' => 'required|url|max:2048'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // VALIDASI: Hanya bisa upload file link jika song sudah approved atau arrangement rejected
            $allowedStatusesForUpload = ['song_approved', 'arrangement_in_progress', 'arrangement_rejected', 'rejected'];
            if (!in_array($arrangement->status, $allowedStatusesForUpload)) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot upload file link. Song proposal must be approved first by Producer. Current status: '{$arrangement->status}'.",
                    'hint' => 'Wait for Producer to approve your song proposal before uploading arrangement file.'
                ], 400);
            }
            
            // Determine new status based on current status
            $newStatus = 'arrangement_in_progress'; // Default setelah upload file
            if ($arrangement->status === 'song_approved') {
                // Jika song sudah approved, set ke in_progress (perlu submit manual)
                $newStatus = 'arrangement_in_progress';
            } elseif (in_array($arrangement->status, ['arrangement_rejected', 'rejected'])) {
                // Jika arrangement ditolak, setelah upload file status tetap rejected
                // Music Arranger perlu submit ulang secara manual
                $newStatus = 'arrangement_rejected'; // Tetap rejected sampai di-submit ulang
            }
            
            $arrangement->update([
                'file_link' => $request->file_link,  // New: Store external link
                'status' => $newStatus
            ]);

            return response()->json([
                'success' => true,
                'message' => in_array($arrangement->status, ['arrangement_rejected', 'rejected']) 
                    ? 'File link updated successfully. Please submit the arrangement again for Producer review.'
                    : 'File link updated successfully.',
                'data' => $arrangement->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function submitSongProposal(Request $request, $id): JsonResponse
    {
        try {
            $arrangement = MusicArrangement::findOrFail($id);
            $arrangement->update(['status' => 'song_proposal', 'submitted_at' => now()]);
            return response()->json(['success' => true, 'data' => $arrangement]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function submit(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $arrangement = MusicArrangement::findOrFail($id);

            // Validate Music Arranger is the creator
            if ($arrangement->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You can only submit your own arrangements.'
                ], 403);
            }

            // Allow submit if:
            // 1. Status is song_approved (first time submit)
            // 2. Status is arrangement_in_progress (with or without file)
            // 3. Status is arrangement_rejected or rejected (resubmit after rejection)
            // 4. Status is arrangement_submitted (re-submit)
            $allowedStatuses = [
                'song_approved',
                'arrangement_in_progress',
                'arrangement_rejected',
                'rejected',
                'arrangement_submitted'
            ];

            if (!in_array($arrangement->status, $allowedStatuses)) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot submit arrangement with status '{$arrangement->status}'. Only arrangements with status: " . implode(', ', $allowedStatuses) . " can be submitted.",
                    'hint' => $arrangement->status === 'song_proposal' ? 'Wait for Producer to approve your song proposal first.' : null
                ], 400);
            }

            // VALIDASI: Harus ada file_link atau file_path sebelum submit
            if (!$arrangement->file_link && !$arrangement->file_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please upload the arrangement file link first before submitting.',
                    'hint' => 'Use the upload file endpoint to add your arrangement file link.'
                ], 400);
            }

            // If status is rejected and no file uploaded, require file first (backup check)
            if (in_array($arrangement->status, ['arrangement_rejected', 'rejected']) && !$arrangement->file_path && !$arrangement->file_link) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please upload the arrangement file first before resubmitting.'
                ], 400);
            }

            // Update status to arrangement_submitted
            $arrangement->update([
                'status' => 'arrangement_submitted',
                'submitted_at' => now()
            ]);

            // Notify Producer
            $episode = $arrangement->episode;
            $productionTeam = $episode->program->productionTeam ?? $episode->productionTeam;
            if ($productionTeam && $productionTeam->producer) {
                $isResubmit = in_array($arrangement->getOriginal('status'), ['arrangement_rejected', 'rejected']);
                Notification::create([
                    'user_id' => $productionTeam->producer->id,
                    'type' => 'music_arrangement_submitted',
                    'title' => $isResubmit ? 'Arrangement Diresubmit' : 'Arrangement Baru',
                    'message' => $isResubmit 
                        ? "Music Arranger {$user->name} telah meresubmit arrangement '{$arrangement->song_title}' untuk Episode {$episode->episode_number} setelah ditolak sebelumnya."
                        : "Music Arranger {$user->name} telah mengirim arrangement '{$arrangement->song_title}' untuk Episode {$episode->episode_number}.",
                    'data' => [
                        'arrangement_id' => $arrangement->id,
                        'episode_id' => $episode->id,
                        'is_resubmit' => $isResubmit
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => in_array($arrangement->getOriginal('status'), ['arrangement_rejected', 'rejected'])
                    ? 'Arrangement resubmitted successfully. Producer has been notified.'
                    : 'Arrangement submitted successfully. Producer has been notified.',
                'data' => $arrangement->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function statistics(): JsonResponse
    {
        $user = Auth::user();
        $stats = [
            'total_arrangements' => MusicArrangement::where('created_by', $user->id)->count(),
            'approved' => MusicArrangement::where('created_by', $user->id)->whereIn('status', ['approved', 'arrangement_approved'])->count(),
        ];
        return response()->json(['success' => true, 'data' => $stats]);
    }

    public function getApprovedArrangementsHistory(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }

            // Get all approved arrangements created by this Music Arranger
            // Include both 'arrangement_approved' and 'approved' status
            // Include arrangements that were approved directly AND those that were helped by Sound Engineer
            $query = MusicArrangement::whereIn('status', ['arrangement_approved', 'approved'])
                ->where('created_by', $user->id)
                ->with(['episode', 'createdBy', 'reviewedBy', 'soundEngineerHelper'])
                ->orderBy('reviewed_at', 'desc')
                ->orderBy('created_at', 'desc');

            // Filter by episode if provided
            if ($request->has('episode_id')) {
                $query->where('episode_id', $request->episode_id);
            }

            // Filter by date range if provided
            if ($request->has('date_from')) {
                $query->whereDate('reviewed_at', '>=', $request->date_from);
            }
            if ($request->has('date_to')) {
                $query->whereDate('reviewed_at', '<=', $request->date_to);
            }

            $arrangements = $query->paginate(15);

            Log::info('MusicArranger getApprovedArrangementsHistory', [
                'user_id' => $user->id,
                'total' => $arrangements->total(),
                'arrangements' => $arrangements->map(function ($arr) {
                    return [
                        'id' => $arr->id,
                        'status' => $arr->status,
                        'sound_engineer_helper_id' => $arr->sound_engineer_helper_id,
                        'reviewed_at' => $arr->reviewed_at
                    ];
                })->toArray()
            ]);

            return response()->json([
                'success' => true,
                'data' => $arrangements,
                'message' => 'Approved arrangements retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('MusicArranger getApprovedArrangementsHistory error', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving approved arrangements: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getAvailableSongs(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => \App\Models\Song::where('status', 'available')->get()]);
    }

    public function getAvailableSingers(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true, 
            'data' => \App\Models\Singer::active()->orderBy('name')->get()
        ]);
    }

    public function acceptWork(Request $request, int $id): JsonResponse
    {
        $arrangement = MusicArrangement::findOrFail($id);
        $arrangement->update(['status' => 'arrangement_in_progress']);
        return response()->json(['success' => true, 'data' => $arrangement]);
    }

    public function completeWork(Request $request, int $id): JsonResponse
    {
        $arrangement = MusicArrangement::findOrFail($id);
        $arrangement->update(['status' => 'arrangement_submitted', 'submitted_at' => now()]);
        return response()->json(['success' => true, 'data' => $arrangement]);
    }

    public function downloadFile($id, Request $request)
    {
        $arrangement = MusicArrangement::findOrFail($id);
        $filePath = Storage::disk('public')->path($arrangement->file_path);
        return response()->file($filePath);
    }
}
