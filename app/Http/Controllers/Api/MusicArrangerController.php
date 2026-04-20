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
use App\Helpers\ProgramManagerAuthorization;
use App\Helpers\MusicProgramAuthorization;

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
            
            $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);
            $userRole = strtolower($user->role);
            
            if (!$user || !MusicProgramAuthorization::canUserPerformTask($user, null, 'Music Arranger')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Include soundEngineerHelper relationship untuk menampilkan info Sound Engineer jika ada
            $query = MusicArrangement::with([
                'episode' => fn($q) => $q->withTrashed(),
                'createdBy',
                'reviewedBy',
                'soundEngineerHelper'
            ]);

            // Music Arranger hanya bisa melihat miliknya sendiri
            // Creative dan Producer bisa melihat semua aransemen yang relevan
            if ($userRole === 'music arranger' && !$isProgramManager) {
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
            
            if (!$user || !MusicProgramAuthorization::canUserPerformTask($user, null, 'Music Arranger')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'episode_id' => 'required|exists:episodes,id',
                'song_id' => 'nullable|exists:songs,id',
                'singer_id' => 'nullable|exists:users,id',
                'song_title' => 'required_without:song_id|string|max:255',
                'singer_name' => 'nullable|string|max:255',
                'is_group' => 'nullable|boolean',
                'group_name' => 'nullable|string|max:255',
                'group_members' => 'nullable|array',
                'arrangement_notes' => 'nullable|string',
                'arrangement_file_link' => 'nullable|url'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // ✅ VALIDASI PRODUCER ACCEPTANCE
            // Music Arranger tidak bisa membuat aransemen jika Producer belum menyetujui program
            $episode = Episode::with(['program' => fn($q) => $q->withTrashed()])->withTrashed()->find($request->episode_id);
            if ($episode && $episode->program && !$episode->program->producer_accepted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Program ini belum disetujui oleh Producer. Tunggu Producer menerima program sebelum memulai aransemen.',
                    'program_name' => $episode->program->name
                ], 403);
            }

            // ✅ VALIDASI DUPLIKASI EPISODE
            // Cek apakah episode sudah memiliki arrangement aktif
            // Status yang dianggap 'aktif' (tidak bisa buat arrangement baru):
            // song_proposal, song_approved, arrangement_in_progress, arrangement_submitted, arrangement_approved
            // Status yang boleh buat arrangement baru (sudah selesai/ditolak):
            // song_rejected, arrangement_rejected
            $activeStatuses = [
                'song_proposal',
                'song_approved',
                'arrangement_in_progress',
                'arrangement_submitted',
                'arrangement_approved',
                'approved',
                'draft'
            ];

            $existingArrangement = MusicArrangement::where('episode_id', $request->episode_id)
                ->whereIn('status', $activeStatuses)
                ->first();

            if ($existingArrangement) {
                $episode = Episode::find($request->episode_id);
                $episodeLabel = $episode ? "Episode {$episode->episode_number}" : "Episode ini";
                $statusLabel = [
                    'song_proposal'          => 'sedang dalam review usulan lagu',
                    'song_approved'          => 'usulan lagunya sudah disetujui, menunggu dikerjakan',
                    'arrangement_in_progress'=> 'sedang dalam proses aransemen',
                    'arrangement_submitted'  => 'aransemen sudah disubmit ke Producer',
                    'arrangement_approved'   => 'sudah selesai dan disetujui',
                    'approved'               => 'sudah selesai dan disetujui',
                    'draft'                  => 'masih dalam proses'
                ][$existingArrangement->status] ?? 'sedang dalam pengerjaan';

                return response()->json([
                    'success' => false,
                    'message' => "{$episodeLabel} tidak bisa dipilih karena {$statusLabel}. Silakan pilih episode lain.",
                    'error_code' => 'EPISODE_ALREADY_ASSIGNED',
                    'data' => [
                        'episode_id'           => $request->episode_id,
                        'existing_arrangement_id' => $existingArrangement->id,
                        'current_status'       => $existingArrangement->status
                    ]
                ], 409);
            }

            // Create record
            // Auto-populate titles from DB if IDs are provided but titles are missing
            $songTitle = $request->song_title;
            if (!$songTitle && $request->song_id) {
                $song = \App\Models\Song::find($request->song_id);
                $songTitle = $song ? $song->title : null;
            }

            $singerName = $request->singer_name;
            if (!$singerName && $request->singer_id) {
                $singer = \App\Models\Singer::where('id', $request->singer_id)->first();
                $singerName = $singer ? $singer->name : null;
            }

            $arrangement = MusicArrangement::create([
                'episode_id' => $request->episode_id,
                'created_by' => $user->id,
                'song_id' => $request->song_id,
                'singer_id' => $request->singer_id,
                'song_title' => $songTitle,
                'singer_name' => $singerName,
                'is_group' => $request->is_group ?? false,
                'group_name' => $request->group_name,
                'group_members' => $request->group_members,
                'arrangement_notes' => $request->arrangement_notes,
                'file_link' => $request->arrangement_file_link,
                'status' => $request->arrangement_file_link ? 'arrangement_submitted' : 'song_proposal',
                'arrangement_submitted_at' => $request->arrangement_file_link ? now() : null,
                'submitted_at' => $request->arrangement_file_link ? now() : null
            ]);

            // Notify Producer for review
            $episode = Episode::with(['program' => fn($p) => $p->withTrashed(), 'program.productionTeam' => fn($p) => $p->withTrashed()])->withTrashed()->findOrFail($arrangement->episode_id);
            $producer = $episode->program->productionTeam->producer ?? null;

            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'new_song_proposal',
                    'title' => 'Proposal Lagu Baru',
                    'message' => "Music Arranger mengajukan proposal lagu untuk Episode {$episode->episode_number}",
                    'data' => [
                        'arrangement_id' => $arrangement->id,
                        'episode_id' => $episode->id,
                        'song_title' => $arrangement->song_title
                    ]
                ]);
            }

            // Update episode workflow state ke tahap music_arrangement
            // supaya Active Production bisa melihat bahwa episode ini sudah masuk tahap Music Arranger
            if ($episode && in_array($episode->current_workflow_state, [null, '', 'program_created', 'episode_generated'], true)) {
                try {
                    $workflowService = app(\App\Services\WorkflowStateService::class);
                    $workflowService->updateWorkflowState(
                        $episode,
                        'music_arrangement',
                        'music_arranger',
                        $user->id,
                        'Music arrangement created by Music Arranger',
                        $user->id,
                        [
                            'action' => 'song_proposal_created',
                            'song_title' => $arrangement->song_title,
                            'singer_name' => $arrangement->is_group ? "Group: {$arrangement->group_name}" : $arrangement->singer_name,
                            'is_group' => $arrangement->is_group,
                            'group_members' => $arrangement->group_members
                        ]
                    );
                } catch (\Throwable $e) {
                    // Jangan gagalkan pembuatan arrangement hanya karena update workflow gagal
                    \Log::warning('Failed to update workflow state to music_arrangement', [
                        'episode_id' => $episode->id ?? null,
                        'arrangement_id' => $arrangement->id ?? null,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Load relasi sama seperti index() agar frontend bisa langsung menampilkan data baru tanpa refresh
            $arrangement->load(['episode', 'createdBy', 'reviewedBy', 'soundEngineerHelper', 'song', 'singer']);

            return response()->json([
                'success' => true,
                'data' => $arrangement,
                'message' => 'Music arrangement created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating arrangement: ' . $e->getMessage()
            ], 500);
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
            $arrangement = MusicArrangement::with(['episode' => fn($q) => $q->withTrashed()])->findOrFail($id);

            // Validate Music Arranger is the creator
            if ($arrangement->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You can only update your own arrangements.'
                ], 403);
            }

            // Allow modification if status is draft, song_proposal, or song_rejected
            // Updated: Also allow modification if status is song_approved or arrangement_approved (User can change singer/group)
            $allowedStatuses = ['draft', 'song_proposal', 'song_rejected', 'arrangement_in_progress', 'arrangement_rejected', 'song_approved', 'arrangement_approved'];
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
                'is_group' => 'nullable|boolean',
                'group_name' => 'nullable|string|max:255',
                'group_members' => 'nullable|array',
                'arrangement_notes' => 'nullable|string',
                'arrangement_file_link' => 'nullable|url|max:2048',
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
                'is_group' => $request->has('is_group') ? $request->is_group : $arrangement->is_group,
                'group_name' => $request->has('group_name') ? $request->group_name : $arrangement->group_name,
                'group_members' => $request->has('group_members') ? $request->group_members : $arrangement->group_members,
                'arrangement_notes' => $request->arrangement_notes ?? $arrangement->arrangement_notes,
                'file_link' => $request->arrangement_file_link ?? $arrangement->file_link,
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

    /**
     * Update Arrangement with Link
     * POS /api/live-tv/roles/music-arranger/arrangements/{id}/input-link
     */
    public function inputLink(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !MusicProgramAuthorization::canUserPerformTask($user, null, 'Music Arranger')) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'arrangement_file_link' => 'required|url'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $arrangement = MusicArrangement::with(['episode' => fn($q) => $q->withTrashed()])->findOrFail($id);

            // Access check: only the Music Arranger who created this arrangement can input link
            if ((int) $arrangement->created_by !== (int) $user->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access to this arrangement.'], 403);
            }

            $arrangement->update([
                'file_link' => $request->arrangement_file_link,
                'status' => 'arrangement_submitted',
                'arrangement_submitted_at' => now(),
                'submitted_at' => now()
            ]);

            // Notify Producer for Review
            $episode = Episode::with(['program' => fn($p) => $p->withTrashed(), 'program.productionTeam' => fn($p) => $p->withTrashed()])->withTrashed()->find($arrangement->episode_id);
            $producer = $episode->program->productionTeam->producer ?? null;

            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'arrangement_submitted',
                    'title' => 'Arrangement Berhasil Di-submit',
                    'message' => "Music Arranger telah mengirimkan link arrangement untuk Episode {$episode->episode_number}",
                    'data' => [
                        'arrangement_id' => $arrangement->id,
                        'episode_id' => $episode->id,
                        'file_link' => $request->arrangement_file_link
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $arrangement->load(['song', 'singer', 'episode']),
                'message' => 'Arrangement link submitted successfully.'
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Legacy File Upload Method (Disabled)
     */
    public function uploadFile(Request $request, $id)
    {
        return response()->json([
            'success' => false,
            'message' => 'Physical file uploads are disabled. Please use the link submission endpoint.'
        ], 405);
    }

    public function submitSongProposal(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !MusicProgramAuthorization::canUserPerformTask($user, null, 'Music Arranger')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'song_title' => 'required|string|max:255',
                'singer_name' => 'nullable|string|max:255',
                'is_group' => 'nullable|boolean',
                'group_name' => 'nullable|string|max:255',
                'group_members' => 'nullable|array',
                'notes' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $arrangement = MusicArrangement::with(['episode' => fn($q) => $q->withTrashed()])->findOrFail($id);

            // Access check: only creator can submit proposal
            if ((int) $arrangement->created_by !== (int) $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You can only submit your own song proposal.'
                ], 403);
            }

            // Must be assigned to a production team (episode team or program team)
            $episode = Episode::with([
                'productionTeam' => fn($q) => $q->withTrashed(),
                'program' => fn($q) => $q->withTrashed(),
                'program.productionTeam' => fn($q) => $q->withTrashed()
            ])->withTrashed()->find($arrangement->episode_id);
            if (!$episode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Episode not found for this arrangement.'
                ], 404);
            }

            $teamOnEpisode = $episode->productionTeam;
            $teamOnProgram = $episode->program ? $episode->program->productionTeam : null;
            $team = $teamOnEpisode ?: $teamOnProgram;

            if (!$team || !$team->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Production team not found for this episode/program. Please ask Manager Program to assign a production team first.'
                ], 404);
            }

            if ((int) $team->music_arranger_id !== (int) $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You are not assigned as Music Arranger for this production team.'
                ], 403);
            }

            // Only allow submit if not already submitted/approved
            if (!in_array($arrangement->status, ['draft', 'revised', 'song_rejected', 'rejected', 'song_proposal', 'song_approved', 'arrangement_approved'])) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot submit song proposal with status '{$arrangement->status}'."
                ], 400);
            }

            $arrangement->update([
                'song_title' => $request->song_title,
                'singer_name' => $request->singer_name,
                'is_group' => $request->is_group ?? false,
                'group_name' => $request->group_name,
                'group_members' => $request->group_members,
                'notes' => $request->notes ?? $arrangement->notes,
                'status' => 'song_proposal',
                'submitted_at' => now()
            ]);

            // Notify Producer
            $producer = $team->producer ?? null;
            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'song_proposal_submitted',
                    'title' => 'Usulan Lagu & Penyanyi Baru',
                    'message' => "Music Arranger mengajukan usulan lagu '{$arrangement->song_title}'" . ($arrangement->singer_name ? " dengan penyanyi '{$arrangement->singer_name}'" : '') . " untuk Episode {$episode->episode_number}.",
                    'data' => [
                        'arrangement_id' => $arrangement->id,
                        'episode_id' => $episode->id,
                        'program_id' => $episode->program_id,
                        'status' => 'song_proposal'
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $arrangement->fresh(['song', 'singer', 'episode']),
                'message' => 'Song proposal submitted successfully. Producer has been notified.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function submit(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $arrangement = MusicArrangement::with(['episode' => fn($q) => $q->withTrashed()])->findOrFail($id);

            if (!$user || !MusicProgramAuthorization::canUserPerformTask($user, $arrangement, 'Music Arranger')) {
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
                'arrangement_submitted_at' => now(),
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

    /**
     * Statistics untuk dashboard Music Arranger.
     * Menghitung per status: total, draft, dikirim (menunggu review), disetujui, ditolak.
     * GET /api/live-tv/music-arranger/statistics
     */
    public function statistics(): JsonResponse
    {
        $user = Auth::user();
        $base = MusicArrangement::where('created_by', $user->id);

        $total = (clone $base)->count();
        $draft = (clone $base)->where('status', 'draft')->count();
        $submitted = (clone $base)->whereIn('status', ['song_proposal', 'arrangement_submitted', 'submitted'])->count();
        $approved = (clone $base)->whereIn('status', ['approved', 'arrangement_approved'])->count();
        $rejected = (clone $base)->whereIn('status', ['song_rejected', 'arrangement_rejected', 'rejected'])->count();

        $stats = [
            'total' => $total,
            'draft' => $draft,
            'submitted' => $submitted,
            'approved' => $approved,
            'rejected' => $rejected,
            // Backward compatibility
            'total_arrangements' => $total,
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
                'arrangements' => collect($arrangements->items())->map(function ($arr) {
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
        try {
            $user = Auth::user();
            $arrangement = MusicArrangement::findOrFail($id);

            if (!$user || !MusicProgramAuthorization::canUserPerformTask($user, $arrangement, 'Music Arranger')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You can only accept your own assigned arrangements.'
                ], 403);
            }

            $arrangement->update(['status' => 'arrangement_in_progress']);

            return response()->json([
                'success' => true,
                'data' => $arrangement,
                'message' => 'Work accepted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function completeWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $arrangement = MusicArrangement::findOrFail($id);

            if (!$user || !MusicProgramAuthorization::canUserPerformTask($user, $arrangement, 'Music Arranger')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You can only complete your own assigned arrangements.'
                ], 403);
            }

            if (!$arrangement->file_link && !$arrangement->file_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please upload the arrangement file or link first before completing work.'
                ], 400);
            }

            $arrangement->update([
                'status' => 'arrangement_submitted', 
                'arrangement_submitted_at' => now(),
                'submitted_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'data' => $arrangement,
                'message' => 'Work completed successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get list of episode IDs that already have an active arrangement
     * Used by frontend to disable already-assigned episodes in dropdown
     * GET /api/live-tv/music-arranger/episodes-status
     */
    public function getEpisodesStatus(Request $request): JsonResponse
    {
        try {
            $activeStatuses = [
                'song_proposal',
                'song_approved',
                'arrangement_in_progress',
                'arrangement_submitted',
                'arrangement_approved',
                'approved',
                'draft'
            ];

            // Get all episodes that have at least one active arrangement
            $activeArrangements = MusicArrangement::whereIn('status', $activeStatuses)
                ->select('episode_id', 'status', 'id', 'created_by', 'song_title')
                ->get()
                ->keyBy('episode_id');

            // Return a map of episode_id => arrangement info
            $result = $activeArrangements->map(function ($arr) {
                return [
                    'has_active_arrangement' => true,
                    'arrangement_id'         => $arr->id,
                    'arrangement_status'     => $arr->status,
                    'song_title'             => $arr->song_title,
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $result,
                'message' => 'Episodes status retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving episodes status: ' . $e->getMessage()
            ], 500);
        }
    }

    public function downloadFile($id, Request $request)
    {
        $arrangement = MusicArrangement::findOrFail($id);
        $filePath = storage_path('app/public/' . $arrangement->file_path);
        return response()->file($filePath);
    }
}
