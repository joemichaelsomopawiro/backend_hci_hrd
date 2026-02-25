<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SoundEngineerEditing;
use App\Models\Episode;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SoundEngineerEditingController extends Controller
{
    /**
     * Check if user is Sound Engineer (Sound Engineer adalah satu role, editing adalah tugasnya)
     */
    private function isSoundEngineerEditing($user): bool
    {
        if (!$user) {
            return false;
        }
        
        $role = strtolower($user->role ?? '');
        // Sound Engineer OR Production role OR anyone with a music team assignment (for dashboard visibility)
        if (in_array($role, [
            'sound engineer',
            'sound_engineer',
            'production'
        ])) {
            return true;
        }

        // Also allow if has any music team assignment (for visibility across dashboards)
        $hasAssignment = $user->hasAnyMusicTeamAssignment();
        \Illuminate\Support\Facades\Log::info('SoundEngineerEditing Access Check', [
            'user_id' => $user->id,
            'role' => $role,
            'has_assignment' => $hasAssignment
        ]);
        
        return $hasAssignment;
    }

    /**
     * Get all sound engineer editing works
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (!$this->isSoundEngineerEditing($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Your role (' . ($user->role ?? 'unknown') . ') does not have access to Sound Engineer Editing endpoints.'
            ], 403);
        }

        $query = SoundEngineerEditing::with(['episode.program', 'soundEngineer', 'recording']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by episode
        if ($request->has('episode_id')) {
            $query->where('episode_id', $request->episode_id);
        }

        $works = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $works
        ]);
    }

    /**
     * Create new sound engineer editing work
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (!$this->isSoundEngineerEditing($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Your role (' . ($user->role ?? 'unknown') . ') does not have access to Sound Engineer Editing endpoints.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'episode_id' => 'required|exists:episodes,id',
            'vocal_file_path' => 'nullable|string', // Keep for backward compatibility
            'vocal_file_link' => 'nullable|url|max:2048', // New: External storage link
            'editing_notes' => 'nullable|string',
            'estimated_completion' => 'nullable|date'
        ]);
        
        // Require either vocal_file_path or vocal_file_link
        if (!$request->has('vocal_file_path') && !$request->has('vocal_file_link')) {
            return response()->json([
                'success' => false,
                'message' => 'Either vocal_file_path or vocal_file_link is required.'
            ], 422);
        }

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $work = SoundEngineerEditing::create([
            'episode_id' => $request->episode_id,
            'sound_engineer_id' => $user->id,
            'vocal_file_path' => $request->vocal_file_path, // Backward compatibility
            'vocal_file_link' => $request->vocal_file_link, // New: External storage link
            'editing_notes' => $request->editing_notes,
            'estimated_completion' => $request->estimated_completion,
            'status' => 'in_progress',
            'created_by' => $user->id
        ]);

        // Notify Producer
        $this->notifyProducer($work);

        return response()->json([
            'success' => true,
            'message' => 'Sound engineer editing work created successfully',
            'data' => $work->load(['episode', 'soundEngineer'])
        ]);
    }

    /**
     * Get specific sound engineer editing work
     */
    public function show($id): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $work = SoundEngineerEditing::with(['episode.program', 'soundEngineer', 'recording'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $work
        ]);
    }

    /**
     * Accept work - Sound Engineer terima tugas editing
     * POST /api/live-tv/sound-engineer-editing/works/{id}/accept-work
     */
    public function acceptWork(Request $request, $id): JsonResponse
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            if (!$this->isSoundEngineerEditing($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Your role (' . ($user->role ?? 'unknown') . ') does not have access to Sound Engineer Editing endpoints.'
                ], 403);
            }

            $work = SoundEngineerEditing::with(['episode.program', 'soundEngineer', 'recording'])->findOrFail($id);

            // Check if work is assigned to this user or user is in the production team
            if ($work->sound_engineer_id !== $user->id) {
                // Check if user is in the production team for this episode
                $episode = $work->episode;
                if ($episode && $episode->program && $episode->program->productionTeam) {
                    $hasAccess = $episode->program->productionTeam->members()
                        ->where('user_id', $user->id)
                        ->where('role', 'sound_eng')
                        ->where('is_active', true)
                        ->exists();
                    
                    if (!$hasAccess) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: This editing work is not assigned to you.'
                        ], 403);
                    }
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized: This editing work is not assigned to you.'
                    ], 403);
                }
            }

            // Check if work can be accepted (status should be in_progress, draft, pending, or revision_needed)
            if (!in_array($work->status, ['in_progress', 'draft', 'pending', 'revision_needed'])) {
                return response()->json([
                    'success' => false,
                    'message' => "Work cannot be accepted. Current status: {$work->status}"
                ], 400);
            }

            // Update work status to in_progress and assign to user
            // If status was revision_needed, reset submission fields
            $updateData = [
                'status' => 'in_progress',
                'sound_engineer_id' => $user->id
            ];
            
            // Reset submission fields if work was rejected (revision_needed)
            if ($work->status === 'revision_needed') {
                $updateData['rejected_by'] = null;
                $updateData['rejected_at'] = null;
                $updateData['rejection_reason'] = null;
                $updateData['submitted_at'] = null; // Reset submitted_at for resubmission
            }
            
            $work->update($updateData);

            // Notify Producer
            $episode = $work->episode;
            $productionTeam = $episode->program->productionTeam;
            $producer = $productionTeam ? $productionTeam->producer : null;
            
            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'sound_engineer_editing_accepted',
                    'title' => 'Sound Engineer Editing Work Accepted',
                    'message' => "Sound Engineer {$user->name} telah menerima tugas editing untuk Episode {$episode->episode_number}.",
                    'data' => [
                        'editing_id' => $work->id,
                        'episode_id' => $episode->id,
                        'sound_engineer_id' => $user->id
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Editing work accepted successfully. You can now proceed with editing.',
                'data' => $work->fresh(['episode.program', 'soundEngineer', 'recording'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error accepting work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update sound engineer editing work
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $work = SoundEngineerEditing::findOrFail($id);

        if (!$this->isSoundEngineerEditing($user) && $work->sound_engineer_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You can only update your own editing work.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'vocal_file_path' => 'nullable|string', // Backward compatibility
            'vocal_file_link' => 'nullable|string|max:2048', // External storage link
            'final_file_path' => 'nullable|string', // Backward compatibility
            'final_file_link' => 'nullable|string|max:2048', // External storage link
            'editing_notes' => 'nullable|string',
            'estimated_completion' => 'nullable|date',
            'status' => 'nullable|in:in_progress,completed,revision_needed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updateData = $request->only([
                'vocal_file_path',
                'vocal_file_link',
                'final_file_path',
                'final_file_link',
                'editing_notes',
                'estimated_completion'
            ]);

            // Only update status if it's provided and not null
            if ($request->has('status') && $request->status !== null) {
                $updateData['status'] = $request->status;
            }

            $work->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Sound engineer editing work updated successfully',
                'data' => $work->load(['episode.program', 'soundEngineer', 'recording'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit work for QC
     */
    public function submit(Request $request, $id): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $work = SoundEngineerEditing::findOrFail($id);

        if (!$this->isSoundEngineerEditing($user) && $work->sound_engineer_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You can only submit your own editing work.'
            ], 403);
        }

        // Check if work can be submitted (must be in_progress, or revision_needed for resubmission)
        if (!in_array($work->status, ['in_progress', 'revision_needed'])) {
            return response()->json([
                'success' => false,
                'message' => "Work cannot be submitted. Current status: {$work->status}. Please accept work first."
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'final_file_path' => 'nullable|string', // Backward compatibility
            'final_file_link' => 'nullable|string|max:2048', // External storage link
            'submission_notes' => 'nullable|string'
        ]);
        
        // Require either final_file_path or final_file_link
        if (!$request->has('final_file_path') && !$request->has('final_file_link')) {
            return response()->json([
                'success' => false,
                'message' => 'Either final_file_path or final_file_link is required.'
            ], 422);
        }

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Reset rejection fields if resubmitting after rejection
        $updateData = [
            'final_file_path' => $request->final_file_path, // Backward compatibility
            'final_file_link' => $request->final_file_link, // New: External storage link
            'submission_notes' => $request->submission_notes,
            'status' => 'submitted',
            'submitted_at' => now()
        ];
        
        if ($work->status === 'revision_needed') {
            $updateData['rejected_by'] = null;
            $updateData['rejected_at'] = null;
            $updateData['rejection_reason'] = null;
        }

        $work->update($updateData);

        // Notify Producer for QC
        $this->notifyProducerForQC($work);

        return response()->json([
            'success' => true,
            'message' => 'Work submitted for QC successfully',
            'data' => $work->load(['episode', 'soundEngineer'])
        ]);
    }

    /**
     * Upload vocal file
     */
    public function uploadVocal(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Physical file uploads are disabled. Please use the inputVocalLink endpoint.'
        ], 405);
    }

    /**
     * Input Vocal Link (External Storage)
     * POST /api/live-tv/sound-engineer-editing/input-vocal-link
     */
    public function inputVocalLink(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            if (!$this->isSoundEngineerEditing($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Your role does not have access to this endpoint.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'vocal_file_link' => 'required|string|max:2048',
                'vocal_file_name' => 'required|string|max:255',
                'vocal_file_size' => 'nullable|integer'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Vocal link received successfully',
                'data' => [
                    'file_link' => $request->vocal_file_link,
                    'file_name' => $request->vocal_file_name,
                    'file_size' => $request->vocal_file_size,
                    'file_path' => $request->vocal_file_link // For backward compatibility in Postman variables
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error processing vocal link: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get statistics
     */
    public function statistics(): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $stats = [
            'total_works' => SoundEngineerEditing::count(),
            'in_progress' => SoundEngineerEditing::where('status', 'in_progress')->count(),
            'completed' => SoundEngineerEditing::where('status', 'completed')->count(),
            'submitted' => SoundEngineerEditing::where('status', 'submitted')->count(),
            'revision_needed' => SoundEngineerEditing::where('status', 'revision_needed')->count(),
            'my_works' => SoundEngineerEditing::where('sound_engineer_id', $user->id)->count(),
            'my_completed' => SoundEngineerEditing::where('sound_engineer_id', $user->id)
                ->where('status', 'completed')->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Notify Producer about new work
     */
    private function notifyProducer($work)
    {
        $producers = User::where('role', 'Producer')->get();
        $notifications = [];
        $now = now();
        
        foreach ($producers as $producer) {
            $notifications[] = [
                'user_id' => $producer->id,
                'type' => 'sound_engineer_editing_created',
                'title' => 'Sound Engineer Editing Work Created',
                'message' => "New sound engineer editing work created for episode: {$work->episode->title}",
                'data' => json_encode([ // Encode data to JSON
                    'work_id' => $work->id,
                    'episode_id' => $work->episode_id,
                    'episode_title' => $work->episode->title
                ]),
                'created_at' => $now,
                'updated_at' => $now
            ];
        }

        if (!empty($notifications)) {
            Notification::insert($notifications);
        }
    }

    /**
     * Notify Producer for QC
     */
    private function notifyProducerForQC($work)
    {
        $producers = User::where('role', 'Producer')->get();
        $notifications = [];
        $now = now();
        
        foreach ($producers as $producer) {
            $notifications[] = [
                'user_id' => $producer->id,
                'type' => 'sound_engineer_editing_submitted',
                'title' => 'Sound Engineer Editing Submitted for QC',
                'message' => "Sound engineer editing work submitted for QC: {$work->episode->title}",
                'data' => json_encode([ // Encode data to JSON
                    'work_id' => $work->id,
                    'episode_id' => $work->episode_id,
                    'episode_title' => $work->episode->title
                ]),
                'created_at' => $now,
                'updated_at' => $now
            ];
        }

        if (!empty($notifications)) {
            Notification::insert($notifications);
        }
    }
}











