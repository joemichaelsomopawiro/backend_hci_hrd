<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MusicArrangement;
use App\Models\Episode;
use App\Models\Notification;
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
            
            if ($user->role !== 'Music Arranger') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $query = MusicArrangement::with(['episode', 'createdBy'])
                ->where('created_by', $user->id);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by episode
            if ($request->has('episode_id')) {
                $query->where('episode_id', $request->episode_id);
            }

            // Filter untuk "Terima Pekerjaan" - hanya arrangement dengan song_approved atau song_rejected
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

            $validator = Validator::make($request->all(), [
                'episode_id' => 'required|exists:episodes,id',
                'song_id' => 'nullable|exists:songs,id', // Optional: pilih dari database
                'song_title' => 'required_without:song_id|string|max:255', // Required if song_id not provided
                'singer_id' => 'nullable|exists:users,id', // Optional: pilih singer dari users
                'singer_name' => 'nullable|string|max:255',
                'arrangement_notes' => 'nullable|string',
                'file' => 'nullable|file|mimes:mp3,wav,midi|max:102400', // 100MB max
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Validasi: Music Arranger hanya bisa create arrangement untuk episode dari ProductionTeam mereka
            $episode = Episode::with(['productionTeam.members', 'program.productionTeam.members'])->findOrFail($request->episode_id);
            
            // Cek ProductionTeam dari Episode dulu, jika tidak ada fallback ke Program
            $productionTeam = null;
            
            if ($episode->production_team_id) {
                // Episode punya ProductionTeam langsung
                $productionTeam = $episode->productionTeam;
            } elseif ($episode->program && $episode->program->production_team_id) {
                // Episode tidak punya, ambil dari Program
                $productionTeam = $episode->program->productionTeam;
            }
            
            if (!$productionTeam) {
                return response()->json([
                    'success' => false,
                    'message' => 'Episode tidak memiliki ProductionTeam yang di-assign'
                ], 403);
            }

            // Cek apakah Music Arranger adalah member ProductionTeam dengan role 'musik_arr'
            $isMember = $productionTeam->members()
                ->where('user_id', $user->id)
                ->where('role', 'musik_arr')
                ->where('is_active', true)
                ->exists();

            if (!$isMember) {
                // Debug info untuk troubleshooting
                Log::info('Music Arranger validation failed', [
                    'user_id' => $user->id,
                    'user_role' => $user->role,
                    'episode_id' => $episode->id,
                    'production_team_id' => $productionTeam->id,
                    'team_members' => $productionTeam->members()->pluck('user_id', 'role')->toArray(),
                    'user_memberships' => $productionTeam->members()->where('user_id', $user->id)->get(['role', 'is_active'])
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak di-assign ke ProductionTeam untuk program episode ini. Pastikan Anda sudah di-assign sebagai Music Arranger (musik_arr) di ProductionTeam episode ini.',
                    'debug' => [
                        'production_team_id' => $productionTeam->id,
                        'production_team_name' => $productionTeam->name,
                        'episode_id' => $episode->id,
                        'episode_title' => $episode->title
                    ]
                ], 403);
            }

            // Handle song selection from database
            $songTitle = $request->song_title;
            $songId = $request->song_id;
            
            if ($songId && !$songTitle) {
                // Get song title from database
                $song = \App\Models\Song::find($songId);
                if ($song) {
                    $songTitle = $song->title;
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Song not found in database'
                    ], 404);
                }
            }

            // Handle singer selection from database
            $singerName = $request->singer_name;
            $singerId = $request->singer_id;
            
            if ($singerId && !$singerName) {
                // Get singer name from users
                $singer = \App\Models\User::find($singerId);
                if ($singer) {
                    $singerName = $singer->name;
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Singer not found'
                    ], 404);
                }
            }

            $filePath = null;
            $fileName = null;
            $fileSize = null;
            $mimeType = null;

            if ($request->hasFile('file')) {
                try {
                    // Use secure file upload helper
                    $fileData = \App\Helpers\FileUploadHelper::validateAudioFile($request->file('file'), 100);
                    
                    $filePath = $fileData['file_path'];
                    $fileName = $fileData['file_name'];
                    $fileSize = $fileData['file_size'];
                    $mimeType = $fileData['mime_type'];

                    // Log file upload
                    \App\Helpers\AuditLogger::logFileUpload('audio', $fileData['original_name'], $fileSize, null, $request);
                } catch (\Exception $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'File upload failed: ' . $e->getMessage()
                    ], 422);
                }
            }

            // Determine status based on whether file is uploaded
            // If no file: song_proposal (ajukan lagu & penyanyi)
            // If has file: draft (old workflow for backward compatibility)
            $status = $filePath ? 'draft' : 'song_proposal';

            $arrangement = MusicArrangement::create([
                'episode_id' => $request->episode_id,
                'song_id' => $songId,
                'singer_id' => $singerId,
                'song_title' => \App\Helpers\SecurityHelper::sanitizeString($songTitle),
                'singer_name' => $singerName ? \App\Helpers\SecurityHelper::sanitizeString($singerName) : null,
                'original_song_title' => \App\Helpers\SecurityHelper::sanitizeString($songTitle), // Store original
                'original_singer_name' => $singerName ? \App\Helpers\SecurityHelper::sanitizeString($singerName) : null, // Store original
                'arrangement_notes' => $request->arrangement_notes ? \App\Helpers\SecurityHelper::sanitizeString($request->arrangement_notes) : null,
                'file_path' => $filePath,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
                'status' => $status,
                'created_by' => $user->id,
            ]);

            // Create notification for Producer
            $productionTeam = $episode->productionTeam ?? $episode->program->productionTeam;
            $producer = $productionTeam ? $productionTeam->producer : null;
            
            if ($producer) {
                if ($status === 'song_proposal') {
                    // Notify Producer about song proposal
                    Notification::create([
                        'user_id' => $producer->id,
                        'type' => 'song_proposal_submitted',
                        'title' => 'Usulan Lagu & Penyanyi Baru',
                        'message' => "Music Arranger {$user->name} mengajukan lagu '{$songTitle}'" . ($singerName ? " dengan penyanyi '{$singerName}'" : '') . " untuk Episode {$arrangement->episode->episode_number}.",
                        'data' => [
                            'arrangement_id' => $arrangement->id,
                            'episode_id' => $arrangement->episode_id,
                            'song_title' => $songTitle,
                            'singer_name' => $singerName
                        ]
                    ]);
                } else {
                    // Old workflow: arrangement created with file
            Notification::create([
                    'user_id' => $producer->id,
                'type' => 'music_arrangement_created',
                'title' => 'New Music Arrangement Created',
                    'message' => "New music arrangement '{$arrangement->song_title}' has been created for Episode {$arrangement->episode->episode_number}.",
                'data' => [
                    'arrangement_id' => $arrangement->id,
                    'episode_id' => $arrangement->episode_id
                ]
            ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Music arrangement created successfully',
                'data' => $arrangement->load(['episode', 'createdBy', 'song', 'singer'])
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
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }

            $arrangement = MusicArrangement::with(['episode', 'createdBy'])
                ->where('id', $id)
                ->where('created_by', $user->id)
                ->first();

            if (!$arrangement) {
                return response()->json([
                    'success' => false,
                    'message' => 'Arrangement not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $arrangement
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving arrangement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update Music Arrangement
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }

            $arrangement = MusicArrangement::where('id', $id)
                ->where('created_by', $user->id)
                ->first();

            if (!$arrangement) {
                return response()->json([
                    'success' => false,
                    'message' => 'Arrangement not found'
                ], 404);
            }

            // Allow update if status is draft, song_proposal, song_approved, or arrangement_in_progress
            $allowedStatuses = ['draft', 'song_proposal', 'song_approved', 'arrangement_in_progress'];
            if (!in_array($arrangement->status, $allowedStatuses)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only arrangements with status draft, song_proposal, song_approved, or arrangement_in_progress can be updated'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'song_title' => 'sometimes|string|max:255',
                'singer_name' => 'nullable|string|max:255',
                'arrangement_notes' => 'nullable|string',
                'file' => 'nullable|file|mimes:mp3,wav,midi|max:102400',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = $request->only(['song_title', 'singer_name', 'arrangement_notes']);

            if ($request->hasFile('file')) {
                // Delete old file if exists
                if ($arrangement->file_path && Storage::disk('public')->exists($arrangement->file_path)) {
                    Storage::disk('public')->delete($arrangement->file_path);
                }

                $file = $request->file('file');
                $filePath = $file->store('music-arrangements', 'public');
                $updateData['file_path'] = $filePath;
                $updateData['file_name'] = $file->getClientOriginalName();
                $updateData['file_size'] = $file->getSize();
                $updateData['mime_type'] = $file->getMimeType();
                
                // If updating arrangement file and status is song_approved, change to arrangement_in_progress
                if ($arrangement->status === 'song_approved') {
                    $updateData['status'] = 'arrangement_in_progress';
                }
            }

            $arrangement->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Arrangement updated successfully',
                'data' => $arrangement->load(['episode', 'createdBy'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating arrangement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit Song Proposal (Ajukan lagu & penyanyi ke Producer)
     */
    public function submitSongProposal(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }

            $arrangement = MusicArrangement::where('id', $id)
                ->where('created_by', $user->id)
                ->first();

            if (!$arrangement) {
                return response()->json([
                    'success' => false,
                    'message' => 'Arrangement not found'
                ], 404);
            }

            if ($arrangement->status !== 'song_proposal') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only song proposals can be submitted'
                ], 400);
            }

            $arrangement->update([
                'status' => 'song_proposal', // Tetap song_proposal, sudah di-submit
                'submitted_at' => now()
            ]);

            // Create notification for Producer
            $episode = $arrangement->episode;
            $productionTeam = $episode->productionTeam ?? $episode->program->productionTeam;
            $producer = $productionTeam ? $productionTeam->producer : null;
            
            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'song_proposal_submitted',
                    'title' => 'Usulan Lagu & Penyanyi',
                    'message' => "Music Arranger {$user->name} mengajukan lagu '{$arrangement->song_title}'" . ($arrangement->singer_name ? " dengan penyanyi '{$arrangement->singer_name}'" : '') . " untuk Episode {$episode->episode_number}.",
                    'data' => [
                        'arrangement_id' => $arrangement->id,
                        'episode_id' => $arrangement->episode_id,
                        'song_title' => $arrangement->song_title,
                        'singer_name' => $arrangement->singer_name
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Song proposal submitted successfully',
                'data' => $arrangement->load(['episode', 'createdBy'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting song proposal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit Music Arrangement file for review (setelah song approved)
     */
    public function submit(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }

            $arrangement = MusicArrangement::where('id', $id)
                ->where('created_by', $user->id)
                ->first();

            if (!$arrangement) {
                return response()->json([
                    'success' => false,
                    'message' => 'Arrangement not found'
                ], 404);
            }

            // Check if arrangement file exists
            if (!$arrangement->file_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'Arrangement file is required. Please upload arrangement file first.'
                ], 400);
            }

            // Allow submit from song_approved or arrangement_in_progress status
            if (!in_array($arrangement->status, ['song_approved', 'arrangement_in_progress', 'draft'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only arrangements with approved song and uploaded file can be submitted'
                ], 400);
            }

            $arrangement->update([
                'status' => 'arrangement_submitted',
                'submitted_at' => now()
            ]);

            // Create notification for Producer
            $episode = $arrangement->episode;
            $productionTeam = $episode->productionTeam ?? $episode->program->productionTeam;
            $producer = $productionTeam ? $productionTeam->producer : null;
            
            if ($producer) {
            Notification::create([
                    'user_id' => $producer->id,
                'type' => 'music_arrangement_submitted',
                    'title' => 'Music Arrangement File Submitted for Review',
                    'message' => "Music Arranger {$user->name} telah menyelesaikan arrangement lagu '{$arrangement->song_title}' dan mengirim file untuk review.",
                'data' => [
                    'arrangement_id' => $arrangement->id,
                        'episode_id' => $arrangement->episode_id,
                        'episode_number' => $episode->episode_number
                ]
            ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Arrangement file submitted for review',
                'data' => $arrangement->load(['episode', 'createdBy'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting arrangement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get statistics for Music Arranger
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

            $stats = [
                'total_arrangements' => MusicArrangement::where('created_by', $user->id)->count(),
                'draft' => MusicArrangement::where('created_by', $user->id)->where('status', 'draft')->count(),
                'song_proposal' => MusicArrangement::where('created_by', $user->id)->where('status', 'song_proposal')->count(),
                'song_approved' => MusicArrangement::where('created_by', $user->id)->where('status', 'song_approved')->count(),
                'song_rejected' => MusicArrangement::where('created_by', $user->id)->where('status', 'song_rejected')->count(),
                'arrangement_in_progress' => MusicArrangement::where('created_by', $user->id)->where('status', 'arrangement_in_progress')->count(),
                'arrangement_submitted' => MusicArrangement::where('created_by', $user->id)->whereIn('status', ['submitted', 'arrangement_submitted'])->count(),
                'arrangement_approved' => MusicArrangement::where('created_by', $user->id)->whereIn('status', ['approved', 'arrangement_approved'])->count(),
                'arrangement_rejected' => MusicArrangement::where('created_by', $user->id)->whereIn('status', ['rejected', 'arrangement_rejected'])->count(),
                // Backward compatibility
                'submitted' => MusicArrangement::where('created_by', $user->id)->whereIn('status', ['submitted', 'arrangement_submitted'])->count(),
                'approved' => MusicArrangement::where('created_by', $user->id)->whereIn('status', ['approved', 'arrangement_approved'])->count(),
                'rejected' => MusicArrangement::where('created_by', $user->id)->whereIn('status', ['rejected', 'arrangement_rejected', 'song_rejected'])->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available songs from database (optional)
     * User: "pilih lagu dari database"
     */
    public function getAvailableSongs(Request $request): JsonResponse
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

            $query = \App\Models\Song::where('status', 'available');

            // Search by title or artist
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('artist', 'like', "%{$search}%");
                });
            }

            // Filter by genre
            if ($request->has('genre')) {
                $query->where('genre', $request->genre);
            }

            $songs = $query->orderBy('title', 'asc')->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $songs,
                'message' => 'Available songs retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving songs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available singers/users (optional)
     */
    public function getAvailableSingers(Request $request): JsonResponse
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

            // Get users who can be singers (role Singer or users with singer role)
            $query = \App\Models\User::where(function($q) {
                $q->where('role', 'Singer')
                  ->orWhere('role', 'like', '%singer%');
            });

            // Search by name
            if ($request->has('search')) {
                $query->where('name', 'like', "%{$request->search}%");
            }

            $singers = $query->orderBy('name', 'asc')->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $singers,
                'message' => 'Available singers retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving singers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Terima Pekerjaan - Music Arranger terima pekerjaan setelah song approved/rejected
     * User: "Terima Pekerjaan"
     */
    public function acceptWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Music Arranger') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $arrangement = MusicArrangement::where('id', $id)
                ->where('created_by', $user->id)
                ->firstOrFail();

            // Only allow accept work if status is song_approved, song_rejected, or arrangement_rejected
            if (!in_array($arrangement->status, ['song_approved', 'song_rejected', 'arrangement_rejected'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Work can only be accepted when song is approved/rejected or arrangement is rejected'
                ], 400);
            }

            // Change status to arrangement_in_progress
            $arrangement->update([
                'status' => 'arrangement_in_progress'
            ]);

            return response()->json([
                'success' => true,
                'data' => $arrangement->fresh(['episode', 'createdBy']),
                'message' => 'Work accepted successfully. You can now arrange the song.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error accepting work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Selesaikan Pekerjaan - Music Arranger selesaikan setelah arrange lagu
     * User: "Selesaikan Pekerjaan"
     */
    public function completeWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Music Arranger') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'completion_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $arrangement = MusicArrangement::where('id', $id)
                ->where('created_by', $user->id)
                ->firstOrFail();

            // Only allow complete if status is arrangement_in_progress and file exists
            if ($arrangement->status !== 'arrangement_in_progress') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work can only be completed when arrangement is in progress'
                ], 400);
            }

            if (!$arrangement->file_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'Arrangement file is required before completing work'
                ], 400);
            }

            // Auto-submit arrangement file after completion
            $arrangement->update([
                'status' => 'arrangement_submitted',
                'submitted_at' => now(),
                'arrangement_notes' => $request->completion_notes ? 
                    ($arrangement->arrangement_notes ? $arrangement->arrangement_notes . "\n\n" : '') . "Completion notes: " . $request->completion_notes 
                    : $arrangement->arrangement_notes
            ]);

            // Notify Producer
            $episode = $arrangement->episode;
            $productionTeam = $episode->productionTeam ?? $episode->program->productionTeam;
            $producer = $productionTeam ? $productionTeam->producer : null;
            
            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'music_arrangement_completed',
                    'title' => 'Arrangement Lagu Selesai',
                    'message' => "Music Arranger {$user->name} telah menyelesaikan arrangement lagu '{$arrangement->song_title}' untuk Episode {$episode->episode_number}.",
                    'data' => [
                        'arrangement_id' => $arrangement->id,
                        'episode_id' => $arrangement->episode_id,
                        'completion_notes' => $request->completion_notes
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $arrangement->fresh(['episode', 'createdBy']),
                'message' => 'Work completed successfully. Arrangement file has been submitted for Producer review.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error completing work: ' . $e->getMessage()
            ], 500);
        }
    }
}
