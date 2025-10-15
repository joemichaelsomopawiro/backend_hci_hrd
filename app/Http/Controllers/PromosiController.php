<?php

namespace App\Http\Controllers;

use App\Models\PromosiBTS;
use App\Models\MusicSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class PromosiController extends Controller
{
    /**
     * Get BTS assignments for Promosi
     */
    public function getBTSAssignments(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Promosi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $assignments = PromosiBTS::with(['submission', 'shootingSchedule'])
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
                'message' => 'Error retrieving BTS assignments: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create BTS assignment
     */
    public function createBTS(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Promosi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'submission_id' => 'required|exists:music_submissions,id',
                'shooting_schedule_id' => 'nullable|exists:shooting_run_sheets,id',
                'notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $bts = PromosiBTS::create([
                'submission_id' => $request->submission_id,
                'shooting_schedule_id' => $request->shooting_schedule_id,
                'notes' => $request->notes,
                'status' => 'pending',
                'created_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'BTS assignment created successfully.',
                'data' => $bts->load(['submission', 'shootingSchedule'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating BTS assignment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload BTS video
     */
    public function uploadBTSVideo($id, Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Promosi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $bts = PromosiBTS::findOrFail($id);

            if ($bts->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this BTS assignment.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'bts_video' => 'required|file|mimes:mp4,avi,mov|max:102400' // 100MB max
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $file = $request->file('bts_video');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('bts_videos', $filename, 'public');

            $bts->uploadBTSVideo($path, asset('storage/' . $path));

            return response()->json([
                'success' => true,
                'message' => 'BTS video uploaded successfully.',
                'data' => [
                    'bts_video_path' => $path,
                    'bts_video_url' => asset('storage/' . $path)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading BTS video: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload talent photos
     */
    public function uploadTalentPhotos($id, Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Promosi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $bts = PromosiBTS::findOrFail($id);

            if ($bts->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this BTS assignment.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'talent_photos' => 'required|array|min:1',
                'talent_photos.*' => 'file|mimes:jpg,jpeg,png|max:10240' // 10MB max per photo
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $photos = [];
            foreach ($request->file('talent_photos') as $index => $photo) {
                $filename = time() . '_' . $index . '_' . $photo->getClientOriginalName();
                $path = $photo->storeAs('talent_photos', $filename, 'public');
                
                $photos[] = [
                    'filename' => $filename,
                    'path' => $path,
                    'url' => asset('storage/' . $path),
                    'original_name' => $photo->getClientOriginalName(),
                    'size' => $photo->getSize(),
                    'mime_type' => $photo->getMimeType()
                ];
            }

            $bts->uploadTalentPhotos($photos);

            return response()->json([
                'success' => true,
                'message' => 'Talent photos uploaded successfully.',
                'data' => [
                    'talent_photos' => $photos
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading talent photos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete BTS work
     */
    public function completeBTS($id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Promosi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $bts = PromosiBTS::findOrFail($id);

            if ($bts->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this BTS assignment.'
                ], 403);
            }

            if (!$bts->canBeCompleted()) {
                return response()->json([
                    'success' => false,
                    'message' => 'BTS work cannot be completed. Please ensure BTS video and talent photos are uploaded.'
                ], 400);
            }

            $bts->completeWork();

            return response()->json([
                'success' => true,
                'message' => 'BTS work completed successfully.',
                'data' => $bts->fresh(['submission', 'shootingSchedule'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error completing BTS work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Start BTS work
     */
    public function startBTS($id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Promosi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $bts = PromosiBTS::findOrFail($id);

            if ($bts->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this BTS assignment.'
                ], 403);
            }

            if (!$bts->canBeStarted()) {
                return response()->json([
                    'success' => false,
                    'message' => 'BTS work cannot be started.'
                ], 400);
            }

            $bts->startWork();

            return response()->json([
                'success' => true,
                'message' => 'BTS work started successfully.',
                'data' => $bts->fresh(['submission', 'shootingSchedule'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error starting BTS work: ' . $e->getMessage()
            ], 500);
        }
    }
}
