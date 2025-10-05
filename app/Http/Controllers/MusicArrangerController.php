<?php

namespace App\Http\Controllers;

use App\Models\Song;
use App\Models\Singer;
use App\Models\MusicSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Exception;

/**
 * @property Singer $singer
 * @method Singer getSinger()
 */
class MusicArrangerController extends BaseController
{
    /**
     * Get submissions for Music Arranger
     */
    public function getSubmissions(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Music Arranger') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $query = MusicSubmission::where('music_arranger_id', $user->id)
                ->with(['song', 'proposedSinger', 'approvedSinger']);

            // Filter by status
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('current_state', $request->status);
            }

            // Search functionality
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->whereHas('song', function ($sq) use ($search) {
                        $sq->where('title', 'like', "%{$search}%")
                           ->orWhere('artist', 'like', "%{$search}%");
                    });
                });
            }

            $perPage = $request->get('per_page', 10);
            $submissions = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $submissions->items(),
                'pagination' => [
                    'current_page' => $submissions->currentPage(),
                    'last_page' => $submissions->lastPage(),
                    'per_page' => $submissions->perPage(),
                    'total' => $submissions->total(),
                    'from' => $submissions->firstItem(),
                    'to' => $submissions->lastItem()
                ]
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving submissions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single submission for Music Arranger
     */
    public function getSubmission($id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Music Arranger') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $submission = MusicSubmission::where('music_arranger_id', $user->id)
                ->where('id', $id)
                ->with(['song', 'proposedSinger', 'approvedSinger'])
                ->first();

            if (!$submission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Submission not found.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $submission
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving submission: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get dashboard data for Music Arranger
     */
    public function dashboard(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Music Arranger') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Get statistics
            $totalSubmissions = MusicSubmission::where('music_arranger_id', $user->id)->count();
            $pendingSubmissions = MusicSubmission::where('music_arranger_id', $user->id)
                ->whereIn('current_state', ['submitted', 'producer_review', 'arranging'])
                ->count();
            $completedSubmissions = MusicSubmission::where('music_arranger_id', $user->id)
                ->where('current_state', 'completed')
                ->count();
            $rejectedSubmissions = MusicSubmission::where('music_arranger_id', $user->id)
                ->where('current_state', 'rejected')
                ->count();

            // Get recent submissions
            $recentSubmissions = MusicSubmission::where('music_arranger_id', $user->id)
                ->with(['song', 'proposedSinger', 'approvedSinger'])
                ->latest()
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'statistics' => [
                        'total_submissions' => $totalSubmissions,
                        'pending_submissions' => $pendingSubmissions,
                        'completed_submissions' => $completedSubmissions,
                        'rejected_submissions' => $rejectedSubmissions
                    ],
                    'recent_submissions' => $recentSubmissions
                ]
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving dashboard data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all songs
     */
    public function getSongs(Request $request): JsonResponse
    {
        try {
            $query = Song::available();

            // Search functionality
            if ($request->has('search') && !empty($request->search)) {
                $query->search($request->search);
            }

            // Filter by genre
            if ($request->has('genre') && !empty($request->genre)) {
                $query->where('genre', $request->genre);
            }

            // Get per_page parameter (default 15, max 1000)
            $perPage = min((int) $request->get('per_page', 15), 1000);
            $songs = $query->orderBy('title')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $songs
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving songs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add new song
     */
    public function addSong(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Music Arranger') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }
            
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'artist' => 'required|string|max:255',
                'genre' => 'nullable|string|max:100',
                'lyrics' => 'nullable|string',
                'duration' => 'nullable|string|max:20',
                'key_signature' => 'nullable|string|max:10',
                'bpm' => 'nullable|integer|min:1|max:300',
                'notes' => 'nullable|string',
                'status' => 'nullable|string|in:available,unavailable',
                'audio_file' => 'nullable|file|mimes:mp3,wav,ogg|max:10240' // 10MB max
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $songData = $request->only([
                'title', 'artist', 'genre', 'lyrics', 'duration',
                'key_signature', 'bpm', 'notes', 'status'
            ]);
            $songData['created_by'] = $user->id;
            $songData['updated_by'] = $user->id;
            
            // Set default status if not provided
            if (!isset($songData['status']) || empty($songData['status'])) {
                $songData['status'] = 'available';
            }

            // Handle audio file upload
            if ($request->hasFile('audio_file')) {
                $file = $request->file('audio_file');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('songs', $filename, 'public');
                
                $songData['audio_file_path'] = $path;
                $songData['audio_file_name'] = $file->getClientOriginalName();
                $songData['file_size'] = $file->getSize();
                $songData['mime_type'] = $file->getMimeType();
            }

            $song = Song::create($songData);

            return response()->json([
                'success' => true,
                'message' => 'Song added successfully.',
                'data' => $song
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding song: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update song
     */
    public function updateSong(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Music Arranger') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }
            
            $song = Song::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'artist' => 'required|string|max:255',
                'genre' => 'nullable|string|max:100',
                'lyrics' => 'nullable|string',
                'duration' => 'nullable|string|max:20',
                'key_signature' => 'nullable|string|max:10',
                'bpm' => 'nullable|integer|min:1|max:300',
                'notes' => 'nullable|string',
                'audio_file' => 'nullable|file|mimes:mp3,wav,ogg|max:10240'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $songData = $request->only([
                'title', 'artist', 'genre', 'lyrics', 'duration',
                'key_signature', 'bpm', 'notes'
            ]);
            $songData['updated_by'] = $user->id;

            // Handle audio file upload
            if ($request->hasFile('audio_file')) {
                // Delete old file
            if ($song->audio_file_path) {
                    Storage::disk('public')->delete($song->audio_file_path);
                }

                $file = $request->file('audio_file');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('songs', $filename, 'public');
                
                $songData['audio_file_path'] = $path;
                $songData['audio_file_name'] = $file->getClientOriginalName();
                $songData['file_size'] = $file->getSize();
                $songData['mime_type'] = $file->getMimeType();
            }

            $song->update($songData);

            return response()->json([
                'success' => true,
                'message' => 'Song updated successfully.',
                'data' => $song
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating song: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete song
     */
    public function deleteSong($id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Music Arranger') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $song = Song::findOrFail($id);
            
            // Check if song is being used in submissions
            $submissionCount = MusicSubmission::where('song_id', $id)->count();
            if ($submissionCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete song. It is being used in ' . $submissionCount . ' submission(s).'
                ], 400);
            }

            // Delete audio file
            if ($song->audio_file_path) {
                Storage::disk('public')->delete($song->audio_file_path);
            }

            $song->delete();

            return response()->json([
                'success' => true,
                'message' => 'Song deleted successfully.'
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting song: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all singers (users with role Singer)
     */
    public function getSingers(Request $request): JsonResponse
    {
        try {
            $query = \App\Models\User::where('role', 'Singer');

            // Search functionality
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $singers = $query->orderBy('name')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $singers
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving singers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add new singer
     */
    public function addSinger(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Music Arranger') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'phone' => 'nullable|string|max:20',
                'bio' => 'nullable|string',
                'specialties' => 'nullable|array',
                'specialties.*' => 'string|max:100',
                'profile_picture' => 'nullable|file|image|max:2048' // 2MB max
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $singerData = $request->only([
                'name', 'email', 'phone', 'bio'
            ]);
            
            // Handle specialties - convert string to array if needed
            if ($request->has('specialties')) {
                if (is_string($request->specialties)) {
                    // If frontend sends as string, split by comma
                    $singerData['specialties'] = array_map('trim', explode(',', $request->specialties));
                } else {
                    $singerData['specialties'] = $request->specialties;
                }
            }
            
            $singerData['created_by'] = $user->id;
            $singerData['updated_by'] = $user->id;

            // Handle profile picture upload
            if ($request->hasFile('profile_picture')) {
                $file = $request->file('profile_picture');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('singers', $filename, 'public');
                $singerData['profile_picture'] = $path;
            }

            // Create User with role Singer instead of Singer model
            $singerData['role'] = 'Singer';
            $singerData['password'] = bcrypt('password123'); // Default password
            $singerData['access_level'] = 'employee';
            
            $singer = \App\Models\User::create($singerData);

            return response()->json([
                'success' => true,
                'message' => 'Singer added successfully.',
                'data' => $singer
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding singer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update singer (handle both Singer model and User model)
     */
    public function updateSinger(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Music Arranger') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Try to find in Singer model first
            $singer = Singer::find($id);
            
            if (!$singer) {
                // If not found in Singer model, try User model
                $singer = \App\Models\User::where('id', $id)->where('role', 'Singer')->first();
                
                if (!$singer) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Singer not found.'
                    ], 404);
                }
            }
            
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email,' . $id,
                'phone' => 'nullable|string|max:20',
                'bio' => 'nullable|string',
                'specialties' => 'nullable|array',
                'specialties.*' => 'string|max:100',
                'profile_picture' => 'nullable|file|image|max:2048'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $singerData = $request->only([
                'name', 'email', 'phone', 'bio'
            ]);
            
            // Handle specialties - convert string to array if needed
            if ($request->has('specialties')) {
                if (is_string($request->specialties)) {
                    // If frontend sends as string, split by comma
                    $singerData['specialties'] = array_map('trim', explode(',', $request->specialties));
                } else {
                    $singerData['specialties'] = $request->specialties;
                }
            }

            // Handle profile picture upload
            if ($request->hasFile('profile_picture')) {
                // Delete old file
                if ($singer->profile_picture) {
                    Storage::disk('public')->delete($singer->profile_picture);
                }

                $file = $request->file('profile_picture');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('singers', $filename, 'public');
                $singerData['profile_picture'] = $path;
            }

            $singer->update($singerData);

            return response()->json([
                'success' => true,
                'message' => 'Singer updated successfully.',
                'data' => $singer
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating singer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete singer
     */
    public function deleteSinger($id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Music Arranger') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Try to find in Singer model first
            $singer = Singer::find($id);
            
            if (!$singer) {
                // If not found in Singer model, try User model
                $singer = \App\Models\User::where('id', $id)->where('role', 'Singer')->first();
                
                if (!$singer) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Singer not found.'
                    ], 404);
                }
            }

            // Check if singer is being used in submissions
            $submissionCount = MusicSubmission::where('proposed_singer_id', $id)
                ->orWhere('approved_singer_id', $id)
                ->count();
            
            if ($submissionCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete singer. They are being used in ' . $submissionCount . ' submission(s).'
                ], 400);
            }

            // Delete profile picture
            if ($singer->profile_picture) {
                Storage::disk('public')->delete($singer->profile_picture);
            }

            $singer->delete();

            return response()->json([
                'success' => true,
                'message' => 'Singer deleted successfully.'
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting singer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get song audio
     */
    public function getSongAudio($id): JsonResponse
    {
        try {
            $song = Song::findOrFail($id);
            
            if (!$song->audio_file_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'Audio file not found for this song.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'audio_url' => $song->audio_url,
                    'file_name' => $song->audio_file_name,
                    'file_size' => $song->file_size,
                    'mime_type' => $song->mime_type
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving song audio: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get my music requests (submissions)
     */
    public function getMyRequests(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Music Arranger') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $query = MusicSubmission::where('music_arranger_id', $user->id)
                ->with(['song', 'proposedSinger', 'approvedSinger']);

            // Search functionality
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->whereHas('song', function ($sq) use ($search) {
                        $sq->where('title', 'like', "%{$search}%")
                           ->orWhere('artist', 'like', "%{$search}%");
                    });
                });
            }

            // Filter by status
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('current_state', $request->status);
            }

            $perPage = $request->get('per_page', 10);
            $submissions = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $submissions->items(),
                'pagination' => [
                    'current_page' => $submissions->currentPage(),
                    'last_page' => $submissions->lastPage(),
                    'per_page' => $submissions->perPage(),
                    'total' => $submissions->total(),
                    'from' => $submissions->firstItem(),
                    'to' => $submissions->lastItem()
                ]
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving my requests: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single request
     */
    public function getRequest($id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Music Arranger') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $submission = MusicSubmission::where('id', $id)
                ->where('music_arranger_id', $user->id)
                ->with(['song', 'proposedSinger', 'approvedSinger', 'workflowHistory.actionByUser'])
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $submission
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update request
     */
    public function updateRequest(Request $request, $id): JsonResponse
    {
        // Log semua request untuk debug
        \Illuminate\Support\Facades\Log::info('MusicArrangerController::updateRequest called', [
            'id' => $id,
            'method' => $request->method(),
            'headers' => $request->headers->all(),
            'request_data' => $request->all(),
            'content_type' => $request->header('Content-Type'),
            'user_agent' => $request->header('User-Agent')
        ]);

        try {
            // Step 1: Check user
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
                    'message' => 'Unauthorized access. Role: ' . $user->role
                ], 403);
            }

            // Step 2: Find submission
            $submission = MusicSubmission::find($id);
            if (!$submission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Submission not found with ID: ' . $id
                ], 404);
            }

            // Step 3: Check ownership
            if ($submission->music_arranger_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only update your own submissions. Owner: ' . $submission->music_arranger_id . ', User: ' . $user->id
                ], 403);
            }

            // Step 4: Validate and prepare update data
            $validator = Validator::make($request->all(), [
                'song_id' => 'sometimes|required|integer|exists:songs,id',
                'proposed_singer_id' => 'nullable|integer|exists:users,id',
                'arrangement_notes' => 'nullable|string|max:1000',
                'requested_date' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                \Illuminate\Support\Facades\Log::error('Validation failed in updateRequest', [
                    'errors' => $validator->errors()->toArray(),
                    'request_data' => $request->all()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Prepare update data - include ALL fields that are sent
            $updateData = [];
            if ($request->has('song_id')) {
                $updateData['song_id'] = $request->song_id;
            }
            if ($request->has('proposed_singer_id')) {
                $updateData['proposed_singer_id'] = $request->proposed_singer_id;
            }
            if ($request->has('arrangement_notes')) {
                $updateData['arrangement_notes'] = $request->arrangement_notes;
            }
            if ($request->has('requested_date')) {
                $updateData['requested_date'] = $request->requested_date;
            }

            \Illuminate\Support\Facades\Log::info('Updating submission with data', $updateData);
            \Illuminate\Support\Facades\Log::info('Before update - submission data', [
                'id' => $submission->id,
                'song_id' => $submission->song_id,
                'proposed_singer_id' => $submission->proposed_singer_id,
                'arrangement_notes' => $submission->arrangement_notes
            ]);

            // Perform the update
            $updateResult = $submission->update($updateData);
            
            \Illuminate\Support\Facades\Log::info('Update result', ['success' => $updateResult]);
            \Illuminate\Support\Facades\Log::info('After update - submission data', [
                'id' => $submission->id,
                'song_id' => $submission->song_id,
                'proposed_singer_id' => $submission->proposed_singer_id,
                'arrangement_notes' => $submission->arrangement_notes,
                'requested_date' => $submission->requested_date
            ]);

            // Double check by querying database directly
            $dbCheck = \App\Models\MusicSubmission::find($submission->id);
            \Illuminate\Support\Facades\Log::info('Database verification check', [
                'id' => $dbCheck->id,
                'song_id' => $dbCheck->song_id,
                'proposed_singer_id' => $dbCheck->proposed_singer_id,
                'arrangement_notes' => $dbCheck->arrangement_notes,
                'requested_date' => $dbCheck->requested_date
            ]);

            // Reload submission with all relationships
            $updatedSubmission = $submission->fresh(['song', 'proposedSinger', 'musicArranger']);
            
            return response()->json([
                'success' => true,
                'message' => 'Request updated successfully.',
                'data' => [
                    'id' => $updatedSubmission->id,
                    'music_arranger_id' => $updatedSubmission->music_arranger_id,
                    'song_id' => $updatedSubmission->song_id,
                    'proposed_singer_id' => $updatedSubmission->proposed_singer_id,
                    'arrangement_notes' => $updatedSubmission->arrangement_notes,
                    'requested_date' => $updatedSubmission->requested_date,
                    'current_state' => $updatedSubmission->current_state,
                    'submission_status' => $updatedSubmission->submission_status,
                    'song' => $updatedSubmission->song ? [
                        'id' => $updatedSubmission->song->id,
                        'title' => $updatedSubmission->song->title,
                        'artist' => $updatedSubmission->song->artist,
                        'genre' => $updatedSubmission->song->genre
                    ] : null,
                    'proposed_singer' => $updatedSubmission->proposedSinger ? [
                        'id' => $updatedSubmission->proposedSinger->id,
                        'name' => $updatedSubmission->proposedSinger->name,
                        'email' => $updatedSubmission->proposedSinger->email
                    ] : null,
                    'created_at' => $updatedSubmission->created_at,
                    'updated_at' => $updatedSubmission->updated_at
                ]
            ]);
            
        } catch (Exception $e) {
            \Illuminate\Support\Facades\Log::error('MusicArrangerController::updateRequest error', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error updating request: ' . $e->getMessage(),
                'debug' => [
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => basename($e->getFile()),
                    'request_data' => $request->all()
                ]
            ], 500);
        }
    }

    /**
     * Cancel request
     */
    public function cancelRequest($id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Music Arranger') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $submission = MusicSubmission::where('id', $id)
                ->where('music_arranger_id', $user->id)
                ->firstOrFail();

            if ($submission->current_state === 'completed' || $submission->current_state === 'rejected') {
            return response()->json([
                'success' => false,
                    'message' => 'This submission cannot be cancelled in its current state.'
                ], 400);
            }

            $submission->update([
                'current_state' => 'rejected',
                'submission_status' => 'rejected',
                'rejected_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Request cancelled successfully.'
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error cancelling request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit music request to producer
     */
    public function submitRequest(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Music Arranger') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'song_id' => 'required|exists:songs,id',
                'proposed_singer_id' => 'nullable|exists:users,id',
                'arrangement_notes' => 'nullable|string|max:1000',
                'requested_date' => 'nullable|date|after_or_equal:today'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $submission = MusicSubmission::create([
                'music_arranger_id' => $user->id,
                'song_id' => $request->song_id,
                'proposed_singer_id' => $request->proposed_singer_id,
                'arrangement_notes' => $request->arrangement_notes,
                'requested_date' => $request->requested_date,
                'current_state' => 'submitted',
                'submission_status' => 'pending',
                'submitted_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Music request submitted successfully.',
                'data' => $submission->load(['song', 'proposedSinger', 'musicArranger'])
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test profile URL
     */
    public function testProfileUrl(): JsonResponse
    {
        try {
            $user = Auth::user();

            return response()->json([
                'success' => true,
                'data' => [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role
                ]
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error testing profile URL: ' . $e->getMessage()
            ], 500);
        }
    }
}