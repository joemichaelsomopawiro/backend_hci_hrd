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
                'song_title' => 'required|string|max:255',
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

            $filePath = null;
            $fileName = null;
            $fileSize = null;
            $mimeType = null;

            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $filePath = $file->store('music-arrangements', 'public');
                $fileName = $file->getClientOriginalName();
                $fileSize = $file->getSize();
                $mimeType = $file->getMimeType();
            }

            $arrangement = MusicArrangement::create([
                'episode_id' => $request->episode_id,
                'song_title' => $request->song_title,
                'singer_name' => $request->singer_name,
                'arrangement_notes' => $request->arrangement_notes,
                'file_path' => $filePath,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
                'status' => 'draft',
                'created_by' => $user->id,
            ]);

            // Create notification for Producer
            Notification::create([
                'user_id' => $user->id, // Will be updated to producer ID
                'type' => 'music_arrangement_created',
                'title' => 'New Music Arrangement Created',
                'message' => "New music arrangement '{$arrangement->song_title}' has been created.",
                'data' => [
                    'arrangement_id' => $arrangement->id,
                    'episode_id' => $arrangement->episode_id
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Music arrangement created successfully',
                'data' => $arrangement->load(['episode', 'createdBy'])
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

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
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

            $updateData = $request->only(['title', 'description', 'arrangement_notes']);

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
     * Submit Music Arrangement for review
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

            if ($arrangement->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft arrangements can be submitted'
                ], 400);
            }

            $arrangement->update([
                'status' => 'submitted',
                'submitted_at' => now()
            ]);

            // Create notification for Producer
            Notification::create([
                'user_id' => $user->id, // Will be updated to producer ID
                'type' => 'music_arrangement_submitted',
                'title' => 'Music Arrangement Submitted for Review',
                'message' => "Music arrangement '{$arrangement->title}' has been submitted for review.",
                'data' => [
                    'arrangement_id' => $arrangement->id,
                    'episode_id' => $arrangement->episode_id
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Arrangement submitted for review',
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
                'submitted' => MusicArrangement::where('created_by', $user->id)->where('status', 'submitted')->count(),
                'approved' => MusicArrangement::where('created_by', $user->id)->where('status', 'approved')->count(),
                'rejected' => MusicArrangement::where('created_by', $user->id)->where('status', 'rejected')->count()
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
}
