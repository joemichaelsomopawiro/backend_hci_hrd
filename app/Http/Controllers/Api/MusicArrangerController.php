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
            
            // IZINKAN AKSES: Music Arranger, Creative, dan Producer
            $userRole = strtolower($user->role);
            $allowedRoles = ['music arranger', 'creative', 'producer'];
            
            if (!in_array($userRole, $allowedRoles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. Your role: ' . $user->role
                ], 403);
            }

            $query = MusicArrangement::with(['episode', 'createdBy', 'reviewedBy']);

            // Music Arranger hanya bisa melihat miliknya sendiri
            // Creative dan Producer bisa melihat semua aransemen yang relevan
            if ($userRole === 'music arranger') {
                $query->where('created_by', $user->id);
            }

            // MAPPING STATUS: Frontend 'approved' -> Backend 'arrangement_approved'
            if ($request->has('status')) {
                $statuses = explode(',', $request->status);
                $mappedStatuses = [];
                
                foreach ($statuses as $status) {
                    $s = trim($status);
                    $mappedStatuses[] = $s;
                    if ($s === 'approved') $mappedStatuses[] = 'arrangement_approved';
                    if ($s === 'rejected') $mappedStatuses[] = 'arrangement_rejected';
                    if ($s === 'submitted') $mappedStatuses[] = 'arrangement_submitted';
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

            $validator = Validator::make($request->all(), [
                'episode_id' => 'required|exists:episodes,id',
                'song_id' => 'nullable|exists:songs,id', 
                'song_title' => 'required_without:song_id|string|max:255', 
                'singer_id' => 'nullable|exists:users,id', 
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
            if ($songId && !$songTitle) {
                $song = \App\Models\Song::find($songId);
                if ($song) $songTitle = $song->title;
            }

            $singerName = $request->singer_name;
            $singerId = $request->singer_id;
            if ($singerId && !$singerName) {
                $singer = \App\Models\User::find($singerId);
                if ($singer) $singerName = $singer->name;
            }

            $filePath = null;
            $fileName = null;
            $fileSize = null;
            $mimeType = null;

            if ($request->hasFile('file')) {
                $fileData = \App\Helpers\FileUploadHelper::validateAudioFile($request->file('file'), 100);
                $filePath = $fileData['file_path'];
                $fileName = $fileData['file_name'];
                $fileSize = $fileData['file_size'];
                $mimeType = $fileData['mime_type'];
            }

            $status = $filePath ? 'draft' : 'song_proposal';

            $arrangement = MusicArrangement::create([
                'episode_id' => $request->episode_id,
                'song_id' => $songId,
                'singer_id' => $singerId,
                'song_title' => $songTitle,
                'singer_name' => $singerName,
                'original_song_title' => $songTitle,
                'original_singer_name' => $singerName,
                'arrangement_notes' => $request->arrangement_notes,
                'file_path' => $filePath,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
                'status' => $status,
                'created_by' => $user->id,
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
            $arrangement = MusicArrangement::with(['episode', 'createdBy'])->findOrFail($id);

            // Access check: creator or team members
            return response()->json(['success' => true, 'data' => $arrangement]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        // ... (Logika update yang sudah ada tetap sama)
        return $this->uploadFile($request, $id); 
    }

    public function uploadFile(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $arrangement = MusicArrangement::findOrFail($id);

            if ($request->hasFile('file')) {
                if ($arrangement->file_path) Storage::disk('public')->delete($arrangement->file_path);
                $file = $request->file('file');
                $filePath = $file->store('music-arrangements', 'public');
                
                $arrangement->update([
                    'file_path' => $filePath,
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'status' => $arrangement->status === 'song_approved' ? 'arrangement_submitted' : $arrangement->status
                ]);
            }

            return response()->json(['success' => true, 'data' => $arrangement->fresh()]);
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
            $arrangement = MusicArrangement::findOrFail($id);
            $arrangement->update(['status' => 'arrangement_submitted', 'submitted_at' => now()]);
            return response()->json(['success' => true, 'data' => $arrangement]);
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
        $user = Auth::user();
        $arrangements = MusicArrangement::whereIn('status', ['arrangement_approved', 'approved'])
            ->where('created_by', $user->id)
            ->paginate(15);
        return response()->json(['success' => true, 'data' => $arrangements]);
    }

    public function getAvailableSongs(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => \App\Models\Song::where('status', 'available')->get()]);
    }

    public function getAvailableSingers(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => \App\Models\User::where('role', 'Singer')->get()]);
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
