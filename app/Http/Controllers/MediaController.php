<?php

namespace App\Http\Controllers;

use App\Models\MediaFile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = MediaFile::with(['program', 'episode', 'uploader']);

            // Filter berdasarkan type
            if ($request->has('type')) {
                $query->where('file_type', $request->type);
            }

            // Filter berdasarkan program
            if ($request->has('program_id')) {
                $query->where('program_id', $request->program_id);
            }

            // Filter berdasarkan episode
            if ($request->has('episode_id')) {
                $query->where('episode_id', $request->episode_id);
            }

            // Filter berdasarkan status
            if ($request->has('is_processed')) {
                $query->where('is_processed', $request->is_processed);
            }

            // Search
            if ($request->has('search')) {
                $query->where('filename', 'like', '%' . $request->search . '%');
            }

            $mediaFiles = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $mediaFiles,
                'message' => 'Media files retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving media files: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload media file
     */
    public function upload(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|max:102400', // 100MB max
                'file_type' => 'required|in:thumbnail,bts_video,talent_photo,script,rundown,other',
                'program_id' => 'required|exists:programs,id',
                'episode_id' => 'nullable|exists:episodes,id',
                'description' => 'nullable|string',
                'is_public' => 'boolean'
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
            $path = $file->storeAs('media/program_management', $filename, 'public');

            $mediaFile = MediaFile::create([
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_url' => Storage::url($path),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'file_type' => $request->file_type,
                'program_id' => $request->program_id,
                'episode_id' => $request->episode_id,
                'uploaded_by' => auth()->id(),
                'description' => $request->description,
                'is_public' => $request->is_public ?? false
            ]);

            $mediaFile->load(['program', 'episode', 'uploader']);

            return response()->json([
                'success' => true,
                'data' => $mediaFile,
                'message' => 'Media file uploaded successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading media file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $mediaFile = MediaFile::with(['program', 'episode', 'uploader'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $mediaFile,
                'message' => 'Media file retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving media file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $mediaFile = MediaFile::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'description' => 'nullable|string',
                'is_public' => 'sometimes|boolean',
                'is_processed' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $mediaFile->update($request->all());
            $mediaFile->load(['program', 'episode', 'uploader']);

            return response()->json([
                'success' => true,
                'data' => $mediaFile,
                'message' => 'Media file updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating media file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $mediaFile = MediaFile::findOrFail($id);

            // Delete file from storage
            if (Storage::disk('public')->exists($mediaFile->file_path)) {
                Storage::disk('public')->delete($mediaFile->file_path);
            }

            $mediaFile->delete();

            return response()->json([
                'success' => true,
                'message' => 'Media file deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting media file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get media files by type
     */
    public function getByType(Request $request, string $type): JsonResponse
    {
        try {
            $mediaFiles = MediaFile::with(['program', 'episode', 'uploader'])
                ->where('file_type', $type)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $mediaFiles,
                'message' => 'Media files by type retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving media files by type: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get media files by program
     */
    public function getByProgram(Request $request, string $programId): JsonResponse
    {
        try {
            $mediaFiles = MediaFile::with(['program', 'episode', 'uploader'])
                ->where('program_id', $programId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $mediaFiles,
                'message' => 'Media files by program retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving media files by program: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get media files by episode
     */
    public function getByEpisode(Request $request, string $episodeId): JsonResponse
    {
        try {
            $mediaFiles = MediaFile::with(['program', 'episode', 'uploader'])
                ->where('episode_id', $episodeId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $mediaFiles,
                'message' => 'Media files by episode retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving media files by episode: ' . $e->getMessage()
            ], 500);
        }
    }
}
