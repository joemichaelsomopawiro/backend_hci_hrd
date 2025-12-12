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
     * Get all sound engineer editing works
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($user->role !== 'Sound Engineer Editing') {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $query = SoundEngineerEditing::with(['episode', 'soundEngineer', 'recording']);

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

        if ($user->role !== 'Sound Engineer Editing') {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $validator = Validator::make($request->all(), [
            'episode_id' => 'required|exists:episodes,id',
            'vocal_file_path' => 'required|string',
            'editing_notes' => 'nullable|string',
            'estimated_completion' => 'nullable|date'
        ]);

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
            'vocal_file_path' => $request->vocal_file_path,
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

        $work = SoundEngineerEditing::with(['episode', 'soundEngineer', 'recording'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $work
        ]);
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

        if ($user->role !== 'Sound Engineer Editing' && $work->sound_engineer_id !== $user->id) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $validator = Validator::make($request->all(), [
            'vocal_file_path' => 'nullable|string',
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

        $work->update($request->only([
            'vocal_file_path',
            'editing_notes',
            'estimated_completion',
            'status'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Sound engineer editing work updated successfully',
            'data' => $work->load(['episode', 'soundEngineer'])
        ]);
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

        if ($user->role !== 'Sound Engineer Editing' && $work->sound_engineer_id !== $user->id) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $validator = Validator::make($request->all(), [
            'final_file_path' => 'required|string',
            'submission_notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $work->update([
            'final_file_path' => $request->final_file_path,
            'submission_notes' => $request->submission_notes,
            'status' => 'submitted',
            'submitted_at' => now()
        ]);

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
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:wav,mp3,aiff,flac|max:50000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $file = $request->file('file');
        $filename = time() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('sound_engineer_editing', $filename, 'public');

        return response()->json([
            'success' => true,
            'message' => 'File uploaded successfully',
            'data' => [
                'file_path' => $path,
                'file_name' => $filename,
                'file_size' => $file->getSize()
            ]
        ]);
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
        
        foreach ($producers as $producer) {
            Notification::create([
                'user_id' => $producer->id,
                'type' => 'sound_engineer_editing_created',
                'title' => 'Sound Engineer Editing Work Created',
                'message' => "New sound engineer editing work created for episode: {$work->episode->title}",
                'data' => [
                    'work_id' => $work->id,
                    'episode_id' => $work->episode_id,
                    'episode_title' => $work->episode->title
                ]
            ]);
        }
    }

    /**
     * Notify Producer for QC
     */
    private function notifyProducerForQC($work)
    {
        $producers = User::where('role', 'Producer')->get();
        
        foreach ($producers as $producer) {
            Notification::create([
                'user_id' => $producer->id,
                'type' => 'sound_engineer_editing_submitted',
                'title' => 'Sound Engineer Editing Submitted for QC',
                'message' => "Sound engineer editing work submitted for QC: {$work->episode->title}",
                'data' => [
                    'work_id' => $work->id,
                    'episode_id' => $work->episode_id,
                    'episode_title' => $work->episode->title
                ]
            ]);
        }
    }
}











