<?php

namespace App\Http\Controllers;

use App\Models\SoundEngineerRecording;
use App\Models\MusicSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class SoundEngineerRecordingController extends Controller
{
    /**
     * Get recording assignments for Sound Engineer
     */
    public function getRecordingAssignments(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Sound Engineer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $assignments = SoundEngineerRecording::with(['submission'])
                ->where('created_by', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $assignments
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving recording assignments: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create recording assignment
     */
    public function createRecording(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Sound Engineer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'submission_id' => 'required|exists:music_submissions,id',
                'recording_date' => 'required|date|after:today',
                'recording_location' => 'required|string|max:255',
                'equipment_list' => 'required|array|min:1',
                'equipment_list.*.name' => 'required|string|max:255',
                'equipment_list.*.quantity' => 'required|integer|min:1',
                'equipment_list.*.notes' => 'nullable|string|max:500',
                'recording_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $recording = SoundEngineerRecording::create([
                'submission_id' => $request->submission_id,
                'recording_date' => $request->recording_date,
                'recording_location' => $request->recording_location,
                'equipment_list' => $request->equipment_list,
                'recording_notes' => $request->recording_notes,
                'status' => 'scheduled',
                'created_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Recording assignment created successfully.',
                'data' => $recording->load(['submission'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating recording assignment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload audio files
     */
    public function uploadAudioFiles($id, Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Sound Engineer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $recording = SoundEngineerRecording::findOrFail($id);

            if ($recording->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this recording assignment.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'audio_files' => 'required|array|min:1',
                'audio_files.*' => 'file|mimes:mp3,wav,ogg,aac|max:102400' // 100MB max per file
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $audioFiles = [];
            foreach ($request->file('audio_files') as $index => $audioFile) {
                $filename = time() . '_' . $index . '_' . $audioFile->getClientOriginalName();
                $path = $audioFile->storeAs('audio_recordings', $filename, 'public');
                
                $audioFiles[] = [
                    'filename' => $filename,
                    'path' => $path,
                    'url' => asset('storage/' . $path),
                    'original_name' => $audioFile->getClientOriginalName(),
                    'size' => $audioFile->getSize(),
                    'mime_type' => $audioFile->getMimeType(),
                    'duration' => null // Can be calculated later if needed
                ];
            }

            $recording->uploadAudioFiles($audioFiles);

            return response()->json([
                'success' => true,
                'message' => 'Audio files uploaded successfully.',
                'data' => [
                    'audio_files' => $audioFiles
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading audio files: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete recording
     */
    public function completeRecording($id, Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Sound Engineer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $recording = SoundEngineerRecording::findOrFail($id);

            if ($recording->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this recording assignment.'
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

            if (!$recording->canBeCompleted()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Recording cannot be completed. Please ensure audio files are uploaded.'
                ], 400);
            }

            $recording->completeRecording(
                $recording->audio_files, 
                $request->completion_notes
            );

            return response()->json([
                'success' => true,
                'message' => 'Recording completed successfully.',
                'data' => $recording->fresh(['submission'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error completing recording: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Start recording
     */
    public function startRecording($id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Sound Engineer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $recording = SoundEngineerRecording::findOrFail($id);

            if ($recording->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this recording assignment.'
                ], 403);
            }

            if (!$recording->canBeStarted()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Recording cannot be started.'
                ], 400);
            }

            $recording->startRecording();

            return response()->json([
                'success' => true,
                'message' => 'Recording started successfully.',
                'data' => $recording->fresh(['submission'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error starting recording: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recording details
     */
    public function getRecordingDetails($id): JsonResponse
    {
        try {
            $recording = SoundEngineerRecording::with(['submission', 'createdBy'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $recording
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving recording details: ' . $e->getMessage()
            ], 500);
        }
    }
}
